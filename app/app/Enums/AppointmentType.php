<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentType: string implements HasColor, HasLabel
{
    case Workshop = 'workshop';
    case Callout = 'callout';

    public function getLabel(): string
    {
        return match ($this) {
            self::Workshop => 'Сервис дээр',
            self::Callout => 'Очиж үйлчлэх',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Workshop => 'primary',
            self::Callout => 'warning',
        };
    }
}
