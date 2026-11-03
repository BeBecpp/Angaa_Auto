<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    public function getSubheading(): ?string
    {
        return 'Гараар бүртгэсэн төлбөр (бэлэн/шилжүүлэг/гадны карт). Төлбөрийн системийн баталгаа биш.';
    }
}
