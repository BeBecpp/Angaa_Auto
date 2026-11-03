<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
#[WithoutTimestamps]
#[WithoutIncrementing]
class SystemHeartbeat extends Model
{
    protected $primaryKey = 'name';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['beat_at' => 'datetime'];
    }

    public static function beat(string $name): void
    {
        static::query()->updateOrCreate(['name' => $name], ['beat_at' => now()]);
    }
}
