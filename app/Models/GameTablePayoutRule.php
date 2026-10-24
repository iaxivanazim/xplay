<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameTablePayoutRule extends Model
{
    protected $fillable = ['table_id', 'payout_id', 'is_active', 'seed_value'];

    protected $casts = [
        'is_active'  => 'boolean',
        'seed_value' => 'decimal:2',
    ];

    public function table()
    {
        return $this->belongsTo(GameTable::class);
    }

    public function payoutRule()
    {
        return $this->belongsTo(PayoutRule::class, 'payout_id', 'payout_id');
    }
}
