<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameConfigPayoutRule extends Model
{
    protected $table = 'game_config_payout_rules';

    protected $fillable = [
        'game_config_id',
        'payout_id',
        'is_active',
        'payout_multiplier',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'payout_multiplier' => 'decimal:3',
    ];

    public function gameConfig()
    {
        return $this->belongsTo(GameConfig::class, 'game_config_id');
    }

    public function payoutRule()
    {
        return $this->belongsTo(PayoutRule::class, 'payout_id', 'payout_id');
    }
}
