<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * Domain actions change state outside the page's form; reload the record so header action visibility,
 * badges and infolists reflect the new state immediately.
 */
trait RefreshesRecordAfterActions
{
    protected function afterActionCalled(Action $action): void
    {
        $this->record->refresh();
    }
}
