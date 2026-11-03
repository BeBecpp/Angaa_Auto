<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PlanStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Satisfied = 'satisfied';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Идэвхтэй',
            self::Satisfied => 'Хийгдсэн',
            self::Paused => 'Түр зогсоосон',
            self::Cancelled => 'Цуцалсан',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::Satisfied => 'success',
            self::Paused => 'gray',
            self::Cancelled => 'gray',
        };
    }
}
