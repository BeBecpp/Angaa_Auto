<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DecisionChannel: string implements HasLabel
{
    case Portal = 'portal';
    case Phone = 'phone';
    case InPerson = 'in_person';
    case OwnerOverride = 'owner_override';

    public function getLabel(): string
    {
        return match ($this) {
            self::Portal => 'Хэрэглэгч порталаар',
            self::Phone => 'Утсаар (ажилтан бүртгэсэн)',
            self::InPerson => 'Биечлэн (ажилтан бүртгэсэн)',
            self::OwnerOverride => 'Эзний онцгой зөвшөөрөл',
        };
    }
}
