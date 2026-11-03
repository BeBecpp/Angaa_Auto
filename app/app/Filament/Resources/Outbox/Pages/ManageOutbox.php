<?php

namespace App\Filament\Resources\Outbox\Pages;

use App\Actions\Maintenance\DispatchOutbox;
use App\Actions\Maintenance\EvaluateDuePlans;
use App\Contracts\SmsProvider;
use App\Filament\Resources\Outbox\NotificationOutboxResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageOutbox extends ManageRecords
{
    protected static string $resource = NotificationOutboxResource::class;

    public function getSubheading(): ?string
    {
        $sms = app(SmsProvider::class);

        return match (true) {
            $sms->name() === 'log' && $sms->isAvailable() => 'SMS горим: туршилт (log) — «Туршилтын сануулга — бодитоор илгээгээгүй». Бодит SMS илгээгдэхгүй.',
            $sms->isAvailable() => 'SMS горим: '.$sms->name(),
            default => 'SMS илгээх үйлчилгээ идэвхгүй — мессежүүд «амжилтгүй» болж харагдана, илгээгдэхгүй.',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runNow')->label('Одоо шалгаж илгээх')->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->modalDescription('Хугацаа болсон төлөвлөгөөг шалгаад, дарааллын мессежийг тохирох SMS горимоор боловсруулна (local бол зөвхөн туршилт).')
                ->action(function (): void {
                    $planned = count(app(EvaluateDuePlans::class)->handle());
                    $counts = app(DispatchOutbox::class)->dispatchDue();
                    Notification::make()->success()->title('Боловсрууллаа')
                        ->body('Шалгасан үе: '.$planned.'. Үр дүн: '.(collect($counts)->map(fn (int $count, string $status): string => $status.' '.$count)->join(', ') ?: 'илгээх зүйл алга'))
                        ->send();
                }),
        ];
    }
}
