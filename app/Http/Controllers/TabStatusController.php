<?php

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\TabStatus;
use App\Services\TabStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TabStatusController — recovery API and heartbeat endpoint.
 *
 * The most critical controller for failure management (Phase 3b).
 *
 * GET  /api/v1/tabs/{id}/status     → Full recovery data for terminal restore
 * POST /api/v1/tabs/{id}/heartbeat  → Terminal liveness ping + balance sync check
 *
 * The /status endpoint must be:
 *   - Low-latency (reads tab_statuses directly — no expensive joins)
 *   - No auth friction (terminals must be able to call this even after a crash)
 */
class TabStatusController extends Controller
{
    public function __construct(private TabStatusService $statusService) {}

    // ── Recovery Status (Terminal Reconnect) ──────────────────────

    /**
     * Returns the full current state of a tab for terminal recovery.
     *
     * Called by the terminal on:
     *   - Boot/startup
     *   - Network reconnect
     *   - Any failure condition
     *
     * The recovery_data JSON blob contains everything the terminal needs
     * to restore its exact pre-failure state.
     *
     * GET /api/v1/tabs/{id}/status
     */
    public function status(Request $request, int $id)
    {
        $tab = GameTable::with([
            'gameType',
            'tabStatus.currentSession',
            'activeSession',
            'config.preset',
            'activePayoutRules.payoutRule',
        ])->findOrFail($id);

        $tabStatus = $tab->tabStatus;

        // If tab_statuses row doesn't exist yet (legacy tab), initialise it
        if (!$tabStatus) {
            DB::transaction(fn() => $this->statusService->initialise($tab));
            $tab->refresh()->load('tabStatus');
            $tabStatus = $tab->tabStatus;
        }

        return response()->json([
            'success' => true,
            'tab'     => [
                'id'                => $tab->id,
                'name'              => $tab->table_name,
                'game_type'         => $tab->gameType?->code,
                'game_type_name'    => $tab->gameType?->name,
                'denomination'      => (float) $tab->denomination,
                'bet_index'         => $tab->bet_index,
                'active_bet_range'  => $tab->activeBetRange,
                'status'            => $tabStatus?->status ?? 'idle',
                'lock_status'       => $tabStatus?->lock_status ?? 'unlocked',
                'connection_status' => $tabStatus?->connection_status ?? 'offline',
                'display_colour'    => $tabStatus?->display_colour ?? 'green',
                'active_mac'        => $tab->active_mac,
            ],
            'session' => $tabStatus?->current_session_id ? [
                'session_id'      => $tabStatus->current_session_id,
                'otp'             => $tabStatus->current_otp,
                'started_at'      => $tabStatus->session_started_at?->toIso8601String(),
                'current_balance' => (float) $tabStatus->current_balance,
                'session_buyin'   => (float) $tabStatus->session_buyin,
            ] : null,
            'last_round' => $tabStatus?->last_game_no ? [
                'game_no'        => $tabStatus->last_game_no,
                'bet_amount'     => (float) $tabStatus->last_bet_amount,
                'win_amount'     => (float) $tabStatus->last_win_amount,
                'played_at'      => $tabStatus->last_game_at?->toIso8601String(),
            ] : null,
            'recovery_data'  => $tabStatus?->recovery_data,   // Full blob for terminal restore
            'last_synced_at' => $tabStatus?->last_synced_at?->toIso8601String(),
        ]);
    }

    // ── Heartbeat ─────────────────────────────────────────────────

    /**
     * Terminal liveness ping — checks that the terminal is still online
     * and its reported balance matches the DB balance.
     *
     * Called every 30 seconds by active terminals.
     * If sync_required: true → terminal should call GET /status.
     *
     * POST /api/v1/tabs/{id}/heartbeat
     * Body:
     *   current_balance float   Terminal's current displayed balance
     *   last_game_no    string  Last completed round ID (optional)
     */
    public function heartbeat(Request $request, int $id)
    {
        $tab = GameTable::with('tabStatus')->findOrFail($id);

        $data = $request->validate([
            'current_balance' => 'required|numeric|min:0',
            'last_game_no'    => 'nullable|string|max:50',
        ]);

        // onHeartbeat() updates connection_status + last_synced_at
        // and compares terminal balance vs DB balance
        $result = $this->statusService->onHeartbeat(
            $tab,
            (float) $data['current_balance'],
            $data['last_game_no'] ?? null
        );

        return response()->json([
            'status'       => 'ok',
            'sync_required'=> $result['sync_required'],
            'discrepancy'  => $result['discrepancy'],  // null if no discrepancy
            'server_time'  => now()->toIso8601String(),
        ]);
    }

    // ── Aggregate Dashboard Status (Cashier View) ─────────────────

    /**
     * Returns status for ALL tabs — used to populate the cage dashboard
     * grid and the tabs index list view.
     *
     * Reads from tab_statuses for speed (no history joins).
     *
     * GET /api/v1/tabs/statuses
     * Query: game_type=BAC  (optional filter)
     */
    public function allStatuses(Request $request)
    {
        $query = TabStatus::with(['tab.gameType'])
            ->orderBy('table_id');

        if ($request->game_type) {
            $query->whereHas('tab.gameType', fn($q) => $q->where('code', $request->game_type));
        }

        $statuses = $query->get()->map(fn($s) => [
            'tab_id'            => $s->table_id,
            'tab_name'          => $s->tab?->table_name,
            'game_type'         => $s->tab?->gameType?->code,
            'denomination'      => (float) $s->tab?->denomination,
            'status'            => $s->status,
            'lock_status'       => $s->lock_status,
            'display_colour'    => $s->display_colour,
            'is_enabled'        => $s->is_enabled,
            'has_session'       => $s->has_session,
            'current_otp'       => $s->current_otp,
            'current_balance'   => (float) $s->current_balance,
            'session_buyin'     => (float) $s->session_buyin,
            'shift_total_in'    => (float) $s->shift_total_in,
            'shift_total_out'   => (float) $s->shift_total_out,
            'shift_total_bet'   => (float) $s->shift_total_bet,
            'shift_total_win'   => (float) $s->shift_total_win,
            'shift_games_count' => $s->shift_games_count,
            'shift_last_bet'    => (float) $s->shift_last_bet,
            'last_game_no'      => $s->last_game_no,
            'last_game_at'      => $s->last_game_at?->toIso8601String(),
            'connection_status' => $s->connection_status,
            'active_mac'        => $s->active_mac,
            'last_synced_at'    => $s->last_synced_at?->toIso8601String(),
        ]);

        // Shift-level aggregates for the header bar
        $aggregates = [
            'total_tabs'      => $statuses->count(),
            'active_sessions' => $statuses->where('has_session', true)->count(),
            'total_credits'   => $statuses->sum('current_balance'),
            'shift_total_in'  => $statuses->sum('shift_total_in'),
            'shift_total_out' => $statuses->sum('shift_total_out'),
            'shift_total_bet' => $statuses->sum('shift_total_bet'),
            'shift_total_win' => $statuses->sum('shift_total_win'),
        ];

        return response()->json([
            'success'    => true,
            'aggregates' => $aggregates,
            'tabs'       => $statuses->values(),
        ]);
    }
}
