<?php

namespace App\Support;

/**
 * Mongolian phone normalization. A syntactically valid number is not proof it can receive SMS.
 */
class Phone
{
    /**
     * Normalize to +976XXXXXXXX, or null when the input is not an accepted local number.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        $country = (string) config('angaa.phone.country_code');

        if (strlen($digits) === strlen($country) + 8 && str_starts_with($digits, $country)) {
            $digits = substr($digits, strlen($country));
        }

        if (! preg_match((string) config('angaa.phone.local_pattern'), $digits)) {
            return null;
        }

        return '+'.$country.$digits;
    }

    /**
     * Human display: 9911 2233.
     */
    public static function display(?string $normalized): string
    {
        if ($normalized === null || $normalized === '') {
            return '';
        }

        $local = substr($normalized, -8);

        return substr($local, 0, 4).' '.substr($local, 4);
    }

    /**
     * Masked display for lists and message logs: •••• 2233.
     */
    public static function mask(?string $normalized): string
    {
        if ($normalized === null || $normalized === '') {
            return '';
        }

        return '•••• '.substr($normalized, -4);
    }

    public static function hash(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
