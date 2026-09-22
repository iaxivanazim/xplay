<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * TabSession — tracks a player's cage session on a tab.
 *
 * Created when a cashier processes a Buyin (cash-in).
 * Closed when a cashier processes a Cashout.
 * OTP is issued at session start and remains active until close.
 *
 * @property int    $session_id
 * @property int    $table_id
 * @property string $otp              6-digit session OTP
 * @property float  $buyin_amount     Total cash received from player
 * @property float  $cashout_amount   Total cash paid back to player
 * @property float  $opening_balance  Credits at session start
 * @property float  $closing_balance  Credits at session end
 * @property int|null $opened_by      Cashier user ID
 * @property int|null $closed_by      Cashier user ID
 * @property string $status           active | completed | voided
 * @property \DateTime $opened_at
 * @property \DateTime|null $closed_at
 * @property string|null $reference   Cage receipt reference
 */
class TabSession extends Model
{
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'table_id',
        'otp',
        'buyin_amount',
        'cashout_amount',
        'opening_balance',
        'closing_balance',
        'opened_by',
        'closed_by',
        'status',
        'opened_at',
        'closed_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'buyin_amount'    => 'decimal:2',
        'cashout_amount'  => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function tab()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(TableLedger::class, 'session_id', 'session_id');
    }

    public function tabStatus()
    {
        return $this->hasOne(TabStatus::class, 'current_session_id', 'session_id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTab($query, int $tableId)
    {
        return $query->where('table_id', $tableId);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Net result for this session: buyin - cashout.
     * Positive = house retained money. Negative = player won more than bought in.
     */
    public function netResult(): float
    {
        return (float) $this->buyin_amount - (float) $this->cashout_amount;
    }

    /**
     * Generate a unique 6-digit OTP for a new session.
     * Ensures no collision with currently active OTPs.
     */
    public static function generateOtp(): string
    {
        do {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (
            static::where('otp', $otp)->where('status', 'active')->exists()
        );

        return $otp;
    }
}
