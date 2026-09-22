<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RoulettePreset — configuration for a Roulette game station.
 *
 * Assigned to a game station (Tab/GameTable) via GameTableConfig (polymorphic).
 * Bet limits are stored as pipe-separated strings supporting multiple tiers,
 * e.g. "100|500|1000" — active tier is selected by the station's bet_index.
 *
 * @property int    $id
 * @property string $name
 * @property string $roulette_type   'european' | 'american'
 * @property string $min_bet         Pipe-separated tier values
 * @property string $max_bet         Pipe-separated tier values
 * @property string|null $side_min_bet
 * @property string|null $side_max_bet
 * @property int    $chip_preset_id
 * @property bool   $status
 */
class RoulettePreset extends Model
{
    protected $fillable = [
        'name',
        'roulette_type',
        'min_bet',
        'max_bet',
        'side_min_bet',
        'side_max_bet',
        'chip_preset_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────

    /**
     * The chip denomination preset assigned to this Roulette preset.
     */
    public function chipPreset()
    {
        return $this->belongsTo(Chip::class, 'chip_preset_id');
    }

    /**
     * The game station (Tab/GameTable) this preset is assigned to.
     * Inverse of the polymorphic GameTableConfig → preset morphTo.
     */
    public function tableAssignment()
    {
        return $this->morphOne(GameTableConfig::class, 'preset');
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Parse the pipe-separated min_bet string into an array of numeric tiers.
     * e.g. "100|500|1000" → [100, 500, 1000]
     */
    public function getMinBetTiersAttribute(): array
    {
        return array_map('floatval', explode('|', $this->min_bet));
    }

    /**
     * Parse the pipe-separated max_bet string into an array of numeric tiers.
     */
    public function getMaxBetTiersAttribute(): array
    {
        return array_map('floatval', explode('|', $this->max_bet));
    }

    /**
     * Return the active min/max bet for a given bet_index (1-based).
     * Returns [min, max] or the first tier if index is out of range.
     */
    public function getActiveBetRange(int $betIndex = 1): array
    {
        $minTiers = $this->getMinBetTiersAttribute();
        $maxTiers = $this->getMaxBetTiersAttribute();
        $idx = max(0, $betIndex - 1);

        return [
            'min' => $minTiers[$idx] ?? $minTiers[0],
            'max' => $maxTiers[$idx] ?? $maxTiers[0],
        ];
    }
}
