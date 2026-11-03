<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Customers\CustomerService;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\Notify;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Notify::attempt(fn () => app(CustomerService::class)->createCustomer($data, auth()->user()), 'Үйлчлүүлэгч бүртгэгдлээ');
    }

    protected function getRedirectUrl(): string
    {
        return CustomerResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
