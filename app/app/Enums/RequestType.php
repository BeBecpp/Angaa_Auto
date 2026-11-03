<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasColor, HasLabel
{
    case Workshop = 'workshop';
    case Callback = 'callback';
    case Callout = 'callout';

    public function getLabel(): string
    {
        return match ($this) {
            self::Workshop => 'Сервис дээр ирэх',
            self::Callback => 'Буцааж залгах',
            self::Callout => 'Очиж үйлчлэх',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Workshop => 'primary',
            self::Callback => 'info',
            self::Callout => 'warning',
        };
    }
}
