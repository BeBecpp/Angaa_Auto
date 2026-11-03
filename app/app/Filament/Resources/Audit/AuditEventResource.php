<?php

namespace App\Filament\Resources\Audit;

use App\Enums\StaffRole;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Audit\Pages\ManageAuditEvents;
use App\Models\AuditEvent;
use App\Support\BusinessTime;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only, append-only audit trail (owner).
 */
class AuditEventResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'аудит';

    protected static ?string $pluralModelLabel = 'Аудит';

    protected static ?string $slug = 'audit';

    protected static function viewRoles(): array
    {
        return [StaffRole::Owner];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label('Хэзээ')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'Y-m-d H:i:s'))->sortable(),
                TextColumn::make('actor.name')->label('Хэн')->placeholder('систем / хэрэглэгч'),
                TextColumn::make('action')->label('Үйлдэл')->searchable()->badge()->color('gray'),
                TextColumn::make('subject_type')->label('Объект')->formatStateUsing(fn (?string $state, AuditEvent $record): string => $state ? $state.' #'.$record->subject_id : '—'),
                TextColumn::make('reason')->label('Шалтгаан')->wrap()->placeholder('—'),
                TextColumn::make('context')->label('Дэлгэрэнгүй')->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Объект')->options(fn (): array => AuditEvent::query()->distinct()->pluck('subject_type', 'subject_type')->filter()->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditEvents::route('/')];
    }
}
