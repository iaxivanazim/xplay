<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TableLedger — immutable cage transaction record.
 *
 * Only two transaction types exist in the Virtuals cage system:
 *   BUYIN   — Cashier receives cash, credits the tab, generates OTP session
 *   CASHOUT — Player cashes out; cashier pays back; session closes
 *
 * All monetary flow goes through the cage directly.
 * No chip float, no table float, no fills/credits/drops.
 *
 * Every record is tied to a TabSession via session_id.
 * The otp snapshot is stored for quick lookup without a join.
 */
class TableLedger extends Model
{
    protected $primaryKey = 'txn_id';

    protected $fillable = [
        'table_id',
        'tab_id',
        'session_id',
        'otp',
        'txn_type',         // BUYIN | CASHOUT
        'payment_medium',   // CASH (only)
        'amount',
        'tab_balance',      // Running tab balance after this txn
        'float_balance',    // Legacy field — kept for schema compatibility
        'gameday',
        'processed',
        'reference',
        'initiated_by',
    ];

    protected $casts = [
        'gameday'       => 'date',
        'amount'        => 'decimal:2',
        'tab_balance'   => 'decimal:2',
        'float_balance' => 'decimal:2',
    ];

    const BUYIN   = 'BUYIN';
    const CASHOUT = 'CASHOUT';

    // ── Relations ────────────────────────────────────────────────

    public function gameTable()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }

    public function session()
    {
        return $this->belongsTo(TabSession::class, 'session_id', 'session_id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeForGameday($query, $gameday)
    {
        return $query->where('gameday', $gameday);
    }

    public function scopeForTab($query, $tabId)
    {
        return $query->where('tab_id', $tabId);
    }

    public function scopeBuyins($query)
    {
        return $query->where('txn_type', self::BUYIN);
    }

    public function scopeCashouts($query)
    {
        return $query->where('txn_type', self::CASHOUT);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopePending($query)
    {
        return $query->where('processed', 0);
    }

    public function scopeCompleted($query)
    {
        return $query->where('processed', 1);
    }
}
