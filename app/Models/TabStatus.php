<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TabStatus — the recovery heartbeat row for a tab.
 *
 * ONE row per tab (enforced by unique constraint on table_id).
 * Always reflects the CURRENT state of the tab.
 *
 * CRITICAL: This model must be written in the same DB::transaction()
 * as every primary write (Buyin, Cashout, game round, lock/unlock).
 * Use TabStatusService::sync() to update this model correctly.
 *
 * The recovery_data JSON field contains the complete state needed
 * by a terminal to restore itself after any failure:
 *   - session (OTP, balance, buyin amount)
 *   - config (min/max bet, denomination)
 *   - payout rules
 *   - last round result
 *
 * @property int    $id
 * @property int    $table_id
 * @property string $status              idle|active|locked|break|disabled|disconnected
 * @property string $lock_status         unlocked|locked|break
 * @property int|null $current_session_id
 * @property string|null $current_otp
 * @property \DateTime|null $session_started_at
 * @property float  $current_balance
 * @property float  $session_buyin
 * @property float  $session_cashout
 * @property float  $shift_total_in
 * @property float  $shift_total_out
 * @property float  $shift_total_bet
 * @property float  $shift_total_win
 * @property int    $shift_games_count
 * @property float  $shift_last_bet
 * @property string|null $last_game_no
 * @property \DateTime|null $last_game_at
 * @property float|null $last_bet_amount
 * @property float|null $last_win_amount
 * @property array|null $recovery_data
 * @property \DateTime|null $last_synced_at
 * @property string $connection_status   online|offline|disconnected
 * @property string|null $active_mac
 */
class TabStatus extends Model
{
    protected $table = 'tab_statuses';

    protected $fillable = [
        'table_id',
        'status',
        'lock_status',
        'current_session_id',
        'current_otp',
        'session_started_at',
        'current_balance',
        'session_buyin',
        'session_cashout',
        'shift_total_in',
        'shift_total_out',
        'shift_total_bet',
        'shift_total_win',
        'shift_games_count',
        'shift_last_bet',
        'last_game_no',
        'last_game_at',
        'last_bet_amount',
        'last_win_amount',
        'recovery_data',
        'last_synced_at',
        'connection_status',
        'active_mac',
    ];

    protected $casts = [
        'current_balance'    => 'decimal:2',
        'session_buyin'      => 'decimal:2',
        'session_cashout'    => 'decimal:2',
        'shift_total_in'     => 'decimal:2',
        'shift_total_out'    => 'decimal:2',
        'shift_total_bet'    => 'decimal:2',
        'shift_total_win'    => 'decimal:2',
        'last_bet_amount'    => 'decimal:2',
        'last_win_amount'    => 'decimal:2',
        'shift_last_bet'     => 'decimal:2',
        'recovery_data'      => 'array',
        'session_started_at' => 'datetime',
        'last_game_at'       => 'datetime',
        'last_synced_at'     => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function tab()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }

    public function currentSession()
    {
        return $this->belongsTo(TabSession::class, 'current_session_id', 'session_id');
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function hasActiveSession(): bool
    {
        return !is_null($this->current_session_id) && $this->current_otp !== null;
    }

    public function isOnline(): bool
    {
        return $this->connection_status === 'online';
    }

    public function isLocked(): bool
    {
        return in_array($this->lock_status, ['locked', 'break']);
    }

    /**
     * Derive the display status colour for the cage dashboard tab card.
     * Matches the UI border colours observed in the reference screenshot.
     *
     * green  → idle, online, no session
     * yellow → active session with balance
     * red    → locked / break
     * grey   → disabled / offline
     */
    public function getDisplayColourAttribute(): string
    {
        if ($this->status === 'disabled') return 'grey';
        if (in_array($this->lock_status, ['locked', 'break'])) return 'red';
        if ($this->hasActiveSession() && $this->current_balance > 0) return 'yellow';
        return 'green';
    }

    /**
     * Shorthand flags matching the tab index column headers (E, F, A).
     */
    public function getIsEnabledAttribute(): bool
    {
        return $this->status !== 'disabled';
    }

    public function getHasSessionAttribute(): bool
    {
        return $this->hasActiveSession();
    }
}
