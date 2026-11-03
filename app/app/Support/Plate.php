<?php

namespace App\Support;

/**
 * Plate normalization for staff search. Mongolian Cyrillic, foreign and nonstandard plates are kept;
 * only case and separators are normalized. No Latin→Cyrillic guessing.
 */
class Plate
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = mb_strtoupper(trim($input), 'UTF-8');
        $value = preg_replace('/[\s\-_.·]+/u', '', $value) ?? '';

        return $value === '' ? null : $value;
    }

    public static function display(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', mb_strtoupper(trim($input), 'UTF-8')) ?? '';

        return $value === '' ? null : $value;
    }
}
