<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer MNT arithmetic. Quantities are stored as integer thousandths (quantity_milli).
 * Rounding happens once per line: half-up to the nearest whole tögrög.
 */
class Money
{
    public static function format(?int $mnt): string
    {
        if ($mnt === null) {
            return '—';
        }

        return number_format($mnt, 0, '.', ' ').' ₮';
    }

    /**
     * Parse a user-entered quantity such as "1.5" or "2" into thousandths.
     */
    public static function quantityToMilli(string|int|float $quantity): int
    {
        $text = str_replace([' ', ','], ['', '.'], trim((string) $quantity));

        if (! preg_match('/^\d+(\.\d{1,3})?$/', $text)) {
            throw new InvalidArgumentException('Тоо хэмжээ буруу байна.');
        }

        [$whole, $fraction] = array_pad(explode('.', $text), 2, '');

        return ((int) $whole) * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public static function milliToDisplay(int $milli): string
    {
        $whole = intdiv($milli, 1000);
        $fraction = $milli % 1000;

        return $fraction === 0 ? (string) $whole : $whole.'.'.rtrim(str_pad((string) $fraction, 3, '0', STR_PAD_LEFT), '0');
    }

    /**
     * Line total = round_half_up(quantity × unit price) − discount + tax, never below zero.
     */
    public static function lineTotal(int $quantityMilli, int $unitPriceMnt, int $discountMnt = 0, int $taxMnt = 0): int
    {
        if ($quantityMilli < 0 || $unitPriceMnt < 0 || $discountMnt < 0 || $taxMnt < 0) {
            throw new InvalidArgumentException('Сөрөг дүн зөвшөөрөхгүй.');
        }

        $gross = intdiv($quantityMilli * $unitPriceMnt + 500, 1000);

        return max(0, $gross - $discountMnt) + $taxMnt;
    }
}
