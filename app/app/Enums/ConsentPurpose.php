<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConsentPurpose: string implements HasLabel
{
    case ServiceReminder = 'service_reminder';
    case Marketing = 'marketing';

    public function getLabel(): string
    {
        return match ($this) {
            self::ServiceReminder => 'Үйлчилгээний сануулга',
            self::Marketing => 'Мэдээ, урамшуулал',
        };
    }
}
