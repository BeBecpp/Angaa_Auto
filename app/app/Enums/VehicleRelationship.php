<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VehicleRelationship: string implements HasLabel
{
    case Owner = 'owner';
    case Driver = 'driver';
    case FleetContact = 'fleet_contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Эзэмшигч',
            self::Driver => 'Жолооч',
            self::FleetContact => 'Байгууллагын хариуцагч',
        };
    }
}
