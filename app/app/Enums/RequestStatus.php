<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Closed = 'closed';
    case Spam = 'spam';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Шинэ',
            self::Contacted => 'Холбогдсон',
            self::Converted => 'Цаг/ажил болсон',
            self::Closed => 'Хаасан',
            self::Spam => 'Спам',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::Converted => 'success',
            self::Closed => 'gray',
            self::Spam => 'danger',
        };
    }
}
