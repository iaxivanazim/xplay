<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutRule extends Model
{
    protected $primaryKey = 'payout_id';

    protected $fillable = [
        'game_type_id',
        'bet_name',
        'bet_position',
        'payout_multiplier',
        'min',
        'max',
        'is_active',
        'is_jackpot'
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_jackpot' => 'boolean',
    ];

    /**
     * Get min bet options as an array of numbers.
     */
    public function getMinValuesAttribute(): array
    {
        return $this->parsePipeValues($this->min);
    }

    /**
     * Get max bet options as an array of numbers.
     */
    public function getMaxValuesAttribute(): array
    {
        return $this->parsePipeValues($this->max);
    }

    /**
     * Helper to parse pipe-separated multi-values or single values into floats.
     */
    public function parsePipeValues(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_map(
            fn($v) => (float) trim($v),
            explode('|', $value)
        );
    }

    public function gameType()
    {
        return $this->belongsTo(GameType::class);
    }
}
