<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    case Staff = 'staff';
    case Customer = 'customer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Staff => 'Ажилтан',
            self::Customer => 'Үйлчлүүлэгч',
        };
    }
}
