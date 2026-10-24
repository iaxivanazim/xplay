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
        'is_active',
        'is_jackpot'
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_jackpot' => 'boolean',
    ];

    public function gameType()
    {
        return $this->belongsTo(GameType::class);
    }
}
