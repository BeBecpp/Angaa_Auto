<?php

namespace App\Actions\Scheduling;

use App\Support\DomainException;

/**
 * A bay or mechanic is already reserved/occupied for the requested interval.
 */
class SchedulingConflict extends DomainException {}
