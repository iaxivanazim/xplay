<?php

namespace App\Services;

use App\Models\GameTable;
use App\Models\TabSession;
use App\Models\TabStatus;
use Illuminate\Support\Facades\DB;

/**
 * TabStatusService — maintains the tab_statuses recovery snapshot.
 *
 * USAGE RULE: Call sync() or the specific update method INSIDE the same
 * DB::transaction() as the primary write. Never call it outside a transaction.
 *
 * The tab_statuses row is the single source of truth for failure recovery.
 * Terminals call GET /api/v1/tabs/{id}/status on reconnect and receive
 * the full recovery_data blob to restore their state.
 */
class TabStatusService
{
    /**
     * Initialise a tab_statuses row when a new tab is registered.
     * Called once after GameTable::create().
     */
    public function initialise(GameTable $tab): TabStatus
    {
        return TabStatus::create([
            'table_id'          => $tab->id,
            'status'            => 'idle',
            'lock_status'       => 'unlocked',
            'current_balance'   => 0,
            'connection_status' => 'offline',
            'active_mac'        => $tab->active_mac,
            'last_synced_at'    => now(),
        ]);
    }

    /**
     * Update snapshot on Buyin — session starts, OTP issued, balance set.
     *
     * @param GameTable  $tab
     * @param TabSession $session  The newly created active session
     * @param float      $amount   Buyin amount credited to tab
     */
    public function onBuyin(GameTable $tab, TabSession $session, float $amount): void
    {
        $status = $this->getOrCreate($tab);

        $status->update([
            'status'              => 'active',
            'current_session_id'  => $session->session_id,
            'current_otp'         => $session->otp,
            'session_started_at'  => $session->opened_at,
            'current_balance'     => $amount,
            'session_buyin'       => $amount,
            'session_cashout'     => 0,
            'shift_total_in'      => DB::raw("shift_total_in + {$amount}"),
            'recovery_data'       => $this->buildRecoveryData($tab, $session, $amount),
            'last_synced_at'      => now(),
        ]);
    }

    /**
     * Update snapshot on Cashout — session closes, balance zeroed.
     *
     * @param GameTable  $tab
     * @param TabSession $session  The session being closed
     * @param float      $amount   Cashout amount
     */
    public function onCashout(GameTable $tab, TabSession $session, float $amount): void
    {
        $status = $this->getOrCreate($tab);

        $status->update([
            'status'             => 'idle',
            'current_session_id' => null,
            'current_otp'        => null,
            'session_started_at' => null,
            'current_balance'    => 0,
            'session_buyin'      => 0,
            'session_cashout'    => 0,
            'shift_total_out'    => DB::raw("shift_total_out + {$amount}"),
            'recovery_data'      => $this->buildIdleRecoveryData($tab),
            'last_synced_at'     => now(),
        ]);
    }

    /**
     * Update snapshot after a game round is saved.
     * Called from the game history store endpoint after each round.
     *
     * @param GameTable $tab
     * @param string    $gameNo        Round identifier
     * @param float     $betAmount     Bet placed this round
     * @param float     $winAmount     Win this round (0 if lost)
     * @param float     $currentCredit Tab balance after round
     */
    public function onGameRound(
        GameTable $tab,
        string $gameNo,
        float $betAmount,
        float $winAmount,
        float $currentCredit
    ): void {
        $status  = $this->getOrCreate($tab);
        $session = $tab->activeSession;

        $status->update([
            'current_balance' => $currentCredit,
            'last_game_no'    => $gameNo,
            'last_game_at'    => now(),
            'last_bet_amount' => $betAmount,
            'last_win_amount' => $winAmount,
            'shift_total_bet' => DB::raw("shift_total_bet + {$betAmount}"),
            'shift_total_win' => DB::raw("shift_total_win + {$winAmount}"),
            'shift_games_count' => DB::raw('shift_games_count + 1'),
            'shift_last_bet'  => $betAmount,
            'recovery_data'   => $this->buildRecoveryData($tab, $session, $currentCredit, [
                'game_no'     => $gameNo,
                'bet_amount'  => $betAmount,
                'win_amount'  => $winAmount,
                'completed_at'=> now()->toIso8601String(),
            ]),
            'last_synced_at'  => now(),
        ]);
    }

    /**
     * Update snapshot on lock / unlock / break.
     */
    public function onLockChange(GameTable $tab, string $lockStatus): void
    {
        $status = $this->getOrCreate($tab);

        $newStatus = match ($lockStatus) {
            'locked' => 'locked',
            'break'  => 'break',
            default  => $tab->hasActiveSession() ? 'active' : 'idle',
        };

        $status->update([
            'status'         => $newStatus,
            'lock_status'    => $lockStatus,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Update snapshot on enable / disable tab.
     */
    public function onStatusChange(GameTable $tab, bool $enabled): void
    {
        $status = $this->getOrCreate($tab);

        $status->update([
            'status'         => $enabled ? 'idle' : 'disabled',
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Update snapshot on MAC registration / unregistration.
     */
    public function onMacChange(GameTable $tab, ?string $mac): void
    {
        $status = $this->getOrCreate($tab);
        $status->update([
            'active_mac'     => $mac,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Update snapshot on heartbeat from terminal.
     * Compares reported balance vs DB balance and returns whether a sync is needed.
     *
     * @return array ['sync_required' => bool, 'discrepancy' => float|null]
     */
    public function onHeartbeat(
        GameTable $tab,
        float $reportedBalance,
        ?string $lastGameNo
    ): array {
        $status = $this->getOrCreate($tab);
        $dbBalance = (float) $status->current_balance;
        $discrepancy = abs($dbBalance - $reportedBalance);
        $syncRequired = $discrepancy > 0.01; // 1-cent tolerance

        $status->update([
            'connection_status' => 'online',
            'last_synced_at'    => now(),
        ]);

        // Also update the game_tables row
        $tab->update([
            'last_seen_at'      => now(),
            'connection_status' => 'online',
        ]);

        return [
            'sync_required' => $syncRequired,
            'discrepancy'   => $syncRequired ? $discrepancy : null,
        ];
    }

    /**
     * Reset shift-level aggregates for a new game day.
     * Called at the start of a new game day for all tabs.
     */
    public function resetShiftAggregates(GameTable $tab): void
    {
        TabStatus::where('table_id', $tab->id)->update([
            'shift_total_in'   => 0,
            'shift_total_out'  => 0,
            'shift_total_bet'  => 0,
            'shift_total_win'  => 0,
            'shift_games_count'=> 0,
            'shift_last_bet'   => 0,
            'last_synced_at'   => now(),
        ]);
    }

    // ── Private Helpers ──────────────────────────────────────────

    /**
     * Get or create the tab_statuses row for a tab.
     * Ensures the row always exists (created lazily if tab was registered before Phase 3).
     */
    private function getOrCreate(GameTable $tab): TabStatus
    {
        return TabStatus::firstOrCreate(
            ['table_id' => $tab->id],
            [
                'status'            => $tab->isEnabled() ? 'idle' : 'disabled',
                'lock_status'       => $tab->lock_status ?? 'unlocked',
                'current_balance'   => 0,
                'connection_status' => 'offline',
                'active_mac'        => $tab->active_mac,
                'last_synced_at'    => now(),
            ]
        );
    }

    /**
     * Build the full recovery_data JSON blob for an active session.
     * This is what the terminal deserialises on reconnect.
     */
    public function buildRecoveryData(
        GameTable $tab,
        ?TabSession $session,
        float $currentBalance,
        array $lastRound = []
    ): array {
        $tab->loadMissing(['gameType', 'config.preset', 'activePayoutRules.payoutRule']);

        $payoutRules = $tab->activePayoutRules->map(fn($r) => [
            'bet_name'     => $r->payoutRule?->bet_name,
            'bet_position' => $r->payoutRule?->bet_position,
            'multiplier'   => $r->payoutRule?->payout_multiplier,
        ])->values()->toArray();

        $preset = $tab->config?->preset;

        return [
            'tab_id'        => $tab->id,
            'tab_name'      => $tab->table_name,
            'game_type'     => $tab->gameType?->code,
            'denomination'  => (float) $tab->denomination,
            'session'       => $session ? [
                'session_id'      => $session->session_id,
                'otp'             => $session->otp,
                'started_at'      => $session->opened_at?->toIso8601String(),
                'buyin_amount'    => (float) $session->buyin_amount,
                'current_balance' => $currentBalance,
            ] : null,
            'last_round'    => $lastRound ?: null,
            'config'        => $preset ? [
                'min_bet'       => $preset->min_bet,
                'max_bet'       => $preset->max_bet,
                'bet_index'     => $tab->bet_index ?? 1,
                'active_range'  => $tab->activeBetRange,
            ] : null,
            'payout_rules'  => $payoutRules,
            'snapshot_at'   => now()->toIso8601String(),
            'portal_version'=> '1.0',
        ];
    }

    /**
     * Build a minimal idle recovery payload (no session active).
     */
    private function buildIdleRecoveryData(GameTable $tab): array
    {
        return $this->buildRecoveryData($tab, null, 0);
    }
}
