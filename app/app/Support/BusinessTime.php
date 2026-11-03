<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Storage is UTC; the business operates in Asia/Ulaanbaatar regardless of viewer device.
 */
class BusinessTime
{
    public static function zone(): string
    {
        return (string) config('angaa.timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    public static function local(?CarbonInterface $instant): ?CarbonImmutable
    {
        return $instant === null ? null : CarbonImmutable::instance($instant)->setTimezone(self::zone());
    }

    /**
     * Parse a business-local wall-clock value into a UTC instant.
     */
    public static function fromLocal(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, self::zone())->utc();
    }

    public static function format(?CarbonInterface $instant, string $format = 'Y-m-d H:i'): string
    {
        return $instant === null ? '—' : self::local($instant)->format($format);
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * Mongolian weekday label, Monday = 1.
     */
    public static function weekdayLabel(int $isoWeekday): string
    {
        return ['', 'Даваа', 'Мягмар', 'Лхагва', 'Пүрэв', 'Баасан', 'Бямба', 'Ням'][$isoWeekday] ?? '';
    }
}
