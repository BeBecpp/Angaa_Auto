<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstimateDecisionType: string implements HasColor, HasLabel
{
    case Approved = 'approved';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::Approved => 'Зөвшөөрсөн',
            self::Declined => 'Татгалзсан',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Declined => 'danger',
        };
    }
}
