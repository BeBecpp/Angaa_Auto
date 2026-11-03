<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Serialized writer for critical sections (scheduling, approvals, payments).
 *
 * MySQL: a transaction with deadlock retries; callers lock stable resource rows with lockForUpdate
 * in deterministic order before checking conflicts.
 * SQLite: lockForUpdate is a no-op, so an atomic cross-process cache lock serializes writers for the
 * named section before the transaction body runs. Re-entrant within one process. This is a local
 * runtime strategy; MySQL is the release target and must be verified there.
 */
class Serialized
{
    /**
     * @var array<string, int>
     */
    private static array $held = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(string $section, Closure $callback, int $attempts = 3): mixed
    {
        $outermost = DB::transactionLevel() === 0;

        if (DB::connection()->getDriverName() !== 'sqlite' || isset(self::$held[$section])) {
            return DB::transaction($callback, $outermost ? $attempts : 1);
        }

        $lock = Cache::lock('serialized:'.$section, (int) config('angaa.scheduling.lock_seconds'));

        try {
            return $lock->block(5, function () use ($section, $callback) {
                self::$held[$section] = 1;

                try {
                    return DB::transaction($callback);
                } finally {
                    unset(self::$held[$section]);
                }
            });
        } catch (LockTimeoutException) {
            throw new DomainException('Систем завгүй байна. Түр хүлээгээд дахин оролдоно уу.');
        }
    }
}
