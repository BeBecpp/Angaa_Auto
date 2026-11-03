<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EstimateLineKind: string implements HasLabel
{
    case Labor = 'labor';
    case Part = 'part';

    public function getLabel(): string
    {
        return match ($this) {
            self::Labor => 'Ажлын хөлс',
            self::Part => 'Сэлбэг',
        };
    }
}
