<?php

namespace App\Filament\Resources\Outbox;

use App\Enums\OutboxStatus;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Outbox\Pages\ManageOutbox;
use App\Models\NotificationOutbox;
use App\Support\Audit;
use App\Support\BusinessTime;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Message outbox with truthful states. Simulated ≠ sent; provider accepted ≠ delivered; a manual call is
 * recorded separately and is not an SMS delivery.
 */
class NotificationOutboxResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = NotificationOutbox::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Сануулга';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'мессеж';

    protected static ?string $pluralModelLabel = 'Мессежийн дараалал';

    protected static ?string $slug = 'outbox';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = NotificationOutbox::query()->whereIn('status', [OutboxStatus::Failed, OutboxStatus::Unknown])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_for', 'desc')
            ->columns([
                TextColumn::make('status')->label('Төлөв')->badge(),
                TextColumn::make('template_key')->label('Төрөл')->formatStateUsing(fn (string $state): string => match ($state) {
                    'appointment_reminder' => 'Цагийн сануулга',
                    'service_due' => 'Үйлчилгээний сануулга',
                    default => $state,
                }),
                TextColumn::make('lifecycle_stage')->label('Үе'),
                TextColumn::make('customer.display_name')->label('Үйлчлүүлэгч')->searchable(),
                TextColumn::make('recipient_phone')->label('Хүлээн авагч')->formatStateUsing(fn (string $state): string => Phone::mask($state)),
                TextColumn::make('scheduled_for')->label('Төлөвлөсөн (УБ)')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'm/d H:i'))->sortable(),
                TextColumn::make('attempt_count')->label('Оролдлого'),
                TextColumn::make('skip_reason')->label('Шалтгаан')->placeholder('—')->formatStateUsing(fn (?string $state): string => self::reasonLabel($state)),
                TextColumn::make('last_error_code')->label('Алдаа')->placeholder('—')->toggleable(),
                TextColumn::make('provider_message_id')->label('Лавлах')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('manual_call_completed_at')->label('Гар дуудлага')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'm/d H:i'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Төлөв')->options(OutboxStatus::class)->multiple(),
            ])
            ->recordActions([
                Action::make('reconcile')->label('Тулгах')->icon('heroicon-o-scale')
                    ->visible(fn (NotificationOutbox $record): bool => in_array($record->status, [OutboxStatus::Unknown, OutboxStatus::ProviderAccepted], true))
                    ->schema([
                        Select::make('result')->label('Операторын мэдээллээр шалгасан үр дүн')->required()->options([
                            OutboxStatus::Delivered->value => 'Хүргэгдсэн нь батлагдсан',
                            OutboxStatus::Failed->value => 'Илгээгдээгүй нь батлагдсан',
                        ]),
                        Textarea::make('note')->label('Хэрхэн шалгасан')->required(),
                    ])
                    ->action(function (NotificationOutbox $record, array $data): void {
                        $status = OutboxStatus::from($data['result']);
                        $record->forceFill(['status' => $status, 'delivered_at' => $status === OutboxStatus::Delivered ? now() : null])->save();
                        Audit::record('outbox.reconciled', $record, ['result' => $status->value], $data['note']);
                    }),
                Action::make('retry')->label('Дахин оролдох')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (NotificationOutbox $record): bool => $record->status === OutboxStatus::Failed)
                    ->requiresConfirmation()
                    ->modalDescription('Илгээгдээгүй нь батлагдсан мессежийг л дахин оролдоно. Илгээхийн өмнө зөвшөөрөл, хугацааг дахин шалгана.')
                    ->action(function (NotificationOutbox $record): void {
                        $record->forceFill(['status' => OutboxStatus::Scheduled, 'next_attempt_at' => null, 'scheduled_for' => now()])->save();
                        Audit::record('outbox.retry', $record);
                    }),
                Action::make('called')->label('Утсаар ярьсан')->icon('heroicon-o-phone')->color('gray')
                    ->visible(fn (NotificationOutbox $record): bool => $record->manual_call_completed_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Гар дуудлагыг тусад нь бүртгэнэ; SMS хүргэлт гэж тооцохгүй.')
                    ->action(function (NotificationOutbox $record): void {
                        $record->forceFill(['manual_call_completed_at' => now(), 'manual_call_by' => auth()->id()])->save();
                        Audit::record('outbox.manual_call', $record);
                    }),
            ])
            ->emptyStateHeading('Мессеж алга');
    }

    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            null => '—',
            'no_consent', 'consent_revoked' => 'Зөвшөөрөлгүй / цуцалсан',
            'recipient_changed' => 'Утас солигдсон',
            'ownership_changed' => 'Эзэмшил шилжсэн',
            'appointment_changed', 'rescheduled' => 'Цаг өөрчлөгдсөн',
            'appointment_not_confirmed', 'cancelled', 'no_show' => 'Цаг хүчингүй',
            'too_late', 'expired', 'quiet_hours_no_longer_useful' => 'Хугацаа өнгөрсөн',
            'plan_not_active', 'plan_changed', 'plan_satisfied' => 'Төлөвлөгөө өөрчлөгдсөн',
            'rebooked' => 'Дахин цаг авсан',
            'deferred_daily_cap' => 'Өдрийн хязгаар — хойшилсон',
            default => $reason,
        };
    }

    public static function getPages(): array
    {
        return ['index' => ManageOutbox::route('/')];
    }
}
