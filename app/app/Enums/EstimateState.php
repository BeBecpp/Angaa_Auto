<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstimateState: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Declined = 'declined';
    case Superseded = 'superseded';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Ноорог',
            self::Sent => 'Илгээсэн — хариу хүлээж буй',
            self::Approved => 'Зөвшөөрсөн',
            self::Declined => 'Татгалзсан',
            self::Superseded => 'Шинэ хувилбараар солигдсон',
            self::Expired => 'Хугацаа дууссан',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'warning',
            self::Approved => 'success',
            self::Declined => 'danger',
            self::Superseded => 'gray',
            self::Expired => 'gray',
        };
    }
}
