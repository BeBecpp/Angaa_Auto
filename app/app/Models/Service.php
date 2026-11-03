<?php

namespace App\Models;

use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class Service extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_type' => PriceType::class,
            'active' => 'boolean',
            'published' => 'boolean',
            'is_demo' => 'boolean',
            'price_mnt' => 'integer',
            'estimated_duration_min' => 'integer',
            'buffer_min' => 'integer',
        ];
    }
}
