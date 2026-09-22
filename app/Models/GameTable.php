<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * GameTable — represents a physical/virtual game station (Tab).
 *
 * In the Virtuals cage system this is the primary entity.
 * Each tab is bound to a hardware terminal by MAC address.
 * All tabs of the same game_type share default configuration
 * from the game type's default preset.
 *
 * Denomination: replaces the Chips module. A simple decimal
 * representing the base credit value (e.g. 1.0 = $1 per credit).
 */
class GameTable extends Model
{
    protected $fillable = [
        'table_name',
        'game_type_id',
        'active_mac',
        'float',
        'bet_index',
        'status',
        'lock_status',
        'locked_at',
        'locked_by',
        'denomination',
        'last_seen_at',
        'connection_status',
    ];

    protected $casts = [
        'status'      => 'boolean',
        'denomination'=> 'decimal:4',
        'locked_at'   => 'datetime',
        'last_seen_at'=> 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────

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

    public function tabStatus()
    {
        return $this->hasOne(TabStatus::class, 'table_id');
    }

    public function sessions()
    {
        return $this->hasMany(TabSession::class, 'table_id');
    }

    public function activeSession()
    {
        return $this->hasOne(TabSession::class, 'table_id')
            ->where('status', 'active');
    }

    public function ledger()
    {
        return $this->hasMany(TableLedger::class, 'table_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // ── Computed Helpers ─────────────────────────────────────────

    public function isLocked(): bool
    {
        return in_array($this->lock_status, ['locked', 'break']);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->status;
    }

    public function hasActiveSession(): bool
    {
        return $this->activeSession()->exists();
    }

    public function getCurrentBalanceAttribute(): float
    {
        return (float) ($this->tabStatus?->current_balance ?? 0);
    }

    public function getCurrentOtpAttribute(): ?string
    {
        return $this->tabStatus?->current_otp;
    }

    /**
     * Returns the active bet min/max for the current bet_index tier.
     * Falls back to tier 1 if index is out of range.
     */
    public function getActiveBetRangeAttribute(): array
    {
        $preset = $this->config?->preset;
        if (!$preset) return ['min' => 0, 'max' => 0];

        $index = ($this->bet_index ?? 1) - 1;
        $mins  = explode('|', $preset->min_bet);
        $maxs  = explode('|', $preset->max_bet);

        return [
            'min' => isset($mins[$index]) ? (float) $mins[$index] : (float) $mins[0],
            'max' => isset($maxs[$index]) ? (float) $maxs[$index] : (float) $maxs[0],
        ];
    }

    // Legacy float compatibility (retained for any existing float-related code)
    public function currentFloat()
    {
        return $this->hasOne(TableFloat::class, 'table_id')->whereNull('closed_at');
    }

    public function isFloatOpen(): bool
    {
        return !is_null($this->currentFloat()->first());
    }
}
