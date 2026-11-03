<?php

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

/**
 * The seven admin navigation groups (03_PRODUCT_SPEC §A).
 */
enum NavGroup: string implements HasLabel
{
    case Overview = 'overview';
    case Schedule = 'schedule';
    case Workshop = 'workshop';
    case Customers = 'customers';
    case Finance = 'finance';
    case FollowUp = 'follow_up';
    case Settings = 'settings';

    public function getLabel(): string
    {
        return match ($this) {
            self::Overview => 'Тойм',
            self::Schedule => 'Цагийн хуваарь',
            self::Workshop => 'Засвар',
            self::Customers => 'Үйлчлүүлэгч',
            self::Finance => 'Санхүү',
            self::FollowUp => 'Сануулга',
            self::Settings => 'Тохиргоо',
        };
    }
}
