<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GameTable extends Model
{
    protected $table = 'tabs';

    protected $fillable = [
        'table_name',
        'game_type_id',
        'active_mac',
        'float',
        'bet_index',
        'status',
        'felt_color',
        'shoe_type_id',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function gameType()
    {
        return $this->belongsTo(GameType::class);
    }

    public function config()
    {
        return $this->hasOne(GameTableConfig::class, 'table_id')->with('preset');
    }

    public function payoutRules()
    {
        return $this->hasMany(GameTablePayoutRule::class, 'table_id');
    }

    public function activePayoutRules()
    {
        return $this->hasMany(GameTablePayoutRule::class, 'table_id')
            ->where('is_active', 1)
            ->with('payoutRule');
    }

    public function isFloatOpen(): bool
    {
        return false;
    }

    public function getLiveFloatAttribute(): ?float
    {
        return 0.0;
    }



    public function getActiveBetRangeAttribute(): array
    {
        $preset = $this->config?->preset;
        if (!$preset) return [];

        $index = ($this->bet_index ?? 1) - 1; // convert to 0-based

        $mins = explode('|', $preset->min_bet);
        $maxs = explode('|', $preset->max_bet);

        return [
            'min' => isset($mins[$index]) ? (float)$mins[$index] : (float)$mins[0],
            'max' => isset($maxs[$index]) ? (float)$maxs[$index] : (float)$maxs[0],
        ];
    }
}
