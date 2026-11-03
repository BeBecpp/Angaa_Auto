<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceType: string implements HasLabel
{
    case Fixed = 'fixed';
    case From = 'from';
    case InspectionRequired = 'inspection_required';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Тогтмол үнэ',
            self::From => 'Эхлэх үнэ',
            self::InspectionRequired => 'Үзлэгийн дараа үнэ',
        };
    }
}
