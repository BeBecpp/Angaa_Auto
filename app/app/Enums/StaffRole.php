<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StaffRole: string implements HasColor, HasLabel
{
    case Owner = 'owner';
    case Advisor = 'advisor';
    case Mechanic = 'mechanic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Эзэн / менежер',
            self::Advisor => 'Үйлчилгээний зөвлөх',
            self::Mechanic => 'Механик',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'primary',
            self::Advisor => 'info',
            self::Mechanic => 'gray',
        };
    }
}
