<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Хүсэлт (баталгаажаагүй)',
            self::Confirmed => 'Баталгаажсан',
            self::Arrived => 'Ирсэн',
            self::Fulfilled => 'Дууссан',
            self::Cancelled => 'Цуцалсан',
            self::NoShow => 'Ирээгүй',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Confirmed => 'primary',
            self::Arrived => 'info',
            self::Fulfilled => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
        };
    }
}
