<?php

namespace App\Support;

use BackedEnum;

/**
 * Form layers may hand us a backed enum instance or its raw value; domain code accepts both.
 */
class EnumValue
{
    public static function of(mixed $value): string|int|null
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
