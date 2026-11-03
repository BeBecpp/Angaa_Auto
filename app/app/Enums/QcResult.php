<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QcResult: string implements HasColor, HasLabel
{
    case Pass = 'pass';
    case Fail = 'fail';
    case NotApplicable = 'not_applicable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pass => 'Тэнцсэн',
            self::Fail => 'Тэнцээгүй',
            self::NotApplicable => 'Хамаарахгүй',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pass => 'success',
            self::Fail => 'danger',
            self::NotApplicable => 'gray',
        };
    }
}
