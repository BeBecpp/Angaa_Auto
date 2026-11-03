<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentState: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case PartPaid = 'part_paid';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Төлөгдөөгүй',
            self::PartPaid => 'Хэсэгчлэн төлсөн',
            self::Paid => 'Бүрэн төлсөн',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::PartPaid => 'warning',
            self::Paid => 'success',
        };
    }
}
