<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PreferredWindow: string implements HasLabel
{
    case Morning = 'morning';
    case Midday = 'midday';
    case Afternoon = 'afternoon';
    case Any = 'any';

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => 'Өглөө (09–12)',
            self::Midday => 'Өдөр (12–15)',
            self::Afternoon => 'Үдээс хойш (15–18)',
            self::Any => 'Аль ч цаг',
        };
    }
}
