<?php

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditEvents extends ManageRecords
{
    protected static string $resource = AuditEventResource::class;
}
