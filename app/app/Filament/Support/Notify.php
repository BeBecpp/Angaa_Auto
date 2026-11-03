<?php

namespace App\Filament\Support;

use App\Support\DomainException;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Runs a domain action from a Filament action and turns business-rule rejections into a visible
 * Mongolian notification while keeping the modal open.
 */
class Notify
{
    public static function attempt(Closure $callback, string $success): mixed
    {
        try {
            $result = $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title('Боломжгүй')->body($exception->getMessage())->persistent()->send();

            throw new Halt;
        }

        Notification::make()->success()->title($success)->send();

        return $result;
    }
}
