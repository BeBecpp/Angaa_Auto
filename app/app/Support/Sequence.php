<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Human-readable numbering without max(id)+1 races: one row per key is incremented under a row lock.
 */
class Sequence
{
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key): int {
            DB::table('order_number_sequences')->insertOrIgnore(['key' => $key, 'next_value' => 1]);

            $row = DB::table('order_number_sequences')->where('key', $key)->lockForUpdate()->first();
            $value = (int) $row->next_value;

            DB::table('order_number_sequences')->where('key', $key)->update(['next_value' => $value + 1]);

            return $value;
        });
    }

    /**
     * e.g. WO-2611-0007 (prefix-YYMM-sequence, business timezone month).
     */
    public static function formatted(string $prefix): string
    {
        $period = BusinessTime::now()->format('ym');
        $value = self::next($prefix.'-'.$period);

        return sprintf('%s-%s-%04d', $prefix, $period, $value);
    }
}
