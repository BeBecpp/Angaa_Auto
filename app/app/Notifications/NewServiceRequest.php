<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * In-panel database notification for staff. Contains no phone number or symptom text.
 */
class NewServiceRequest extends Notification
{
    public function __construct(public ServiceRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Шинэ засварын хүсэлт: '.$this->request->reference)
            ->body($this->request->request_type->getLabel())
            ->icon('heroicon-o-inbox-arrow-down')
            ->getDatabaseMessage();
    }
}
