<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Bank = 'bank';
    case CardExternal = 'card_external';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Бэлэн',
            self::Bank => 'Банкны шилжүүлэг',
            self::CardExternal => 'Карт (гадны терминал)',
            self::Manual => 'Бусад / гар бүртгэл',
        };
    }
}
