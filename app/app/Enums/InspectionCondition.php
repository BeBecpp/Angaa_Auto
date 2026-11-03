<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InspectionCondition: string implements HasColor, HasLabel
{
    case Pass = 'pass';
    case Attention = 'attention';
    case Urgent = 'urgent';
    case NotChecked = 'not_checked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pass => 'Хэвийн',
            self::Attention => 'Анхаарах',
            self::Urgent => 'Яаралтай',
            self::NotChecked => 'Шалгаагүй',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pass => 'success',
            self::Attention => 'warning',
            self::Urgent => 'danger',
            self::NotChecked => 'gray',
        };
    }
}
