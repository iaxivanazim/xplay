<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableLedger extends Model
{
    protected $primaryKey = 'txn_id';

    protected $fillable = [
        'table_id',
        'tab_id',
        'txn_type',
        'payment_medium',
        'amount',
        'tab_balance',
        'float_balance',
        'gameday',
        'processed',
        'reference',
        'initiated_by'
    ];

    protected $casts = [
        'gameday'       => 'date',
        'amount'        => 'decimal:2',
        'tab_balance'   => 'decimal:2',
        'float_balance' => 'decimal:2',
    ];

    // txn_types that affect float balance
    const FLOAT_AFFECTING = ['FILL', 'CREDIT', 'DROP', 'ADJUST', 'CASHOUT', 'BUYIN', 'VOID', 'BET'];

    // txn_types that affect tab balance
    const TAB_AFFECTING   = ['BUYIN', 'CASHOUT', 'PAYOUT', 'CREDIT', 'VOID', 'BET'];

    public function tab()
    {
        return $this->belongsTo(Tab::class, 'tab_id');
    }

    public function gameTable()
    {
        return $this->belongsTo(Tab::class, 'tab_id');
    }

    // ── Scopes ──────────────────────────────────────────────────
    public function scopeForGameday($query, $gameday)
    {
        return $query->where('gameday', $gameday);
    }

    public function scopeForTab($query, $tabId)
    {
        return $query->where('tab_id', $tabId);
    }

    public function scopePending($query)
    {
        return $query->where('processed', 0);
    }

    public function scopeClaimed($query)
    {
        return $query->where('processed', 2);
    }

    public function scopeCompleted($query)
    {
        return $query->where('processed', 1);
    }
}
