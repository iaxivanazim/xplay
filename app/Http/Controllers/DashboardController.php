<?php

namespace App\Http\Controllers;

use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\TabGameConfig;
use App\Models\GameConfig;
use App\Models\TableLedger;
use App\Models\TabBalanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * Render the Cashier Cage Dashboard (Grid View matching screenshot).
     */
    public function index()
    {
        Tab::syncStaleConnections();

        $tabs = Tab::with([
            'statusRecord',
            'currentSession',
            'gameConfig.gameType',
            'gameConfigs.gameType',
            'gameType',
        ])->orderBy('tab_number', 'asc')->get();

        $statuses = TabStatus::all();

        // Cage KPIs
        $totalIn       = (float) $statuses->sum('shift_total_in');
        $totalOut      = (float) $statuses->sum('shift_total_out');
        $difference    = $totalIn - $totalOut;
        $totalBet      = (float) $statuses->sum('shift_total_bet');
        $totalWin      = (float) $statuses->sum('shift_total_win');
        $betWinDiff    = $totalBet - $totalWin;
        $machinesCount = $tabs->count();
        $activeCount   = $statuses->where('status', 'active')->count();

        $gameConfigs = GameConfig::where('is_active', 1)->with('gameType')->get();

        return view('dashboard', compact(
            'tabs',
            'totalIn',
            'totalOut',
            'difference',
            'totalBet',
            'totalWin',
            'betWinDiff',
            'machinesCount',
            'activeCount',
            'gameConfigs'
        ));
    }

    /**
     * Fetch full live details for a tab (AJAX for drawer modal).
     */
    public function tabDetails($id)
    {
        $tab = Tab::with([
            'statusRecord',
            'currentSession',
            'gameConfig.gameType',
            'gameConfigs.gameType',
        ])->findOrFail($id);

        $status = $tab->statusRecord;
        $session = $tab->currentSession;

        return response()->json([
            'success'        => true,
            'tab'            => [
                'id'                => $tab->id,
                'tab_number'        => $tab->tab_number,
                'table_name'        => $tab->table_name,
                'ip_address'        => $tab->ip_address,
                'active_mac'        => $tab->active_mac,
                'denomination'      => $tab->denomination,
                'lock_status'       => $tab->lock_status,
                'connection_status' => $tab->isOnline() ? 'online' : 'offline',
                'status'            => $tab->status,
                'game_config_id'    => $tab->game_config_id,
                'game_config_name'  => $tab->gameConfig?->name,
                'game_type'         => $tab->gameConfig?->gameType?->name,
                'assigned_configs'  => $tab->gameConfigs->map(fn($c) => [
                    'id'   => $c->id,
                    'name' => $c->name,
                    'game' => $c->gameType?->name,
                ]),
            ],
            'status'         => [
                'current_balance'   => $status ? (float)$status->current_balance : 0.00,
                'current_otp'       => $status?->current_otp,
                'player_name'       => $status?->player_name,
                'player_phone'      => $status?->player_phone,
                'player_card_id'    => $status?->player_card_id,
                'session_buyin'     => $status ? (float)$status->session_buyin : 0.00,
                'shift_total_in'    => $status ? (float)$status->shift_total_in : 0.00,
                'shift_total_out'   => $status ? (float)$status->shift_total_out : 0.00,
                'shift_games_count' => $status ? (int)$status->shift_games_count : 0,
                'shift_last_bet'    => $status ? (float)$status->shift_last_bet : 0.00,
                'last_game_no'      => $status?->last_game_no,
                'last_game_at'      => $status?->last_game_at?->format('H:i:s'),
                'last_synced_at'    => $status?->last_synced_at?->diffForHumans(),
                'recovery_data'     => $status?->recovery_data,
            ],
            'has_active_session' => ($session !== null || ($status && $status->current_balance > 0)),
        ]);
    }

    /**
     * Cashin from Cashier side (Assign Player & Add Balance).
     */
    public function cashin(Request $request, $id)
    {
        $tab = Tab::findOrFail($id);

        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'player_name'    => 'nullable|string|max:100',
            'player_phone'   => 'nullable|string|max:50',
            'player_card_id' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($request, $tab) {
            $currentUserId = auth()->id() ?? 1;
            $amount        = (float) $request->amount;

            $status = $tab->statusRecord ?? TabStatus::create([
                'tab_id'            => $tab->id,
                'status'            => 'idle',
                'lock_status'       => 'unlocked',
                'current_balance'   => 0,
                'connection_status' => 'online',
            ]);

            $session = $tab->currentSession;
            if (!$session) {
                // OTP is generated by player upon first time locking the tab; remains null at session start
                $otp = null;
                $session = TabSession::create([
                    'tab_id'          => $tab->id,
                    'otp'             => null,
                    'player_name'     => $request->player_name ?? 'Player-' . $tab->tab_number,
                    'player_phone'    => $request->player_phone,
                    'player_card_id'  => $request->player_card_id,
                    'buyin_amount'    => $amount,
                    'cashout_amount'  => 0,
                    'opening_balance' => $amount,
                    'closing_balance' => 0,
                    'opened_by'       => $currentUserId,
                    'status'          => 'active',
                    'opened_at'       => now(),
                ]);
            } else {
                $session->increment('buyin_amount', $amount);
                $otp = $session->otp;
            }

            $prevBalance = (float) $status->current_balance;
            $newBalance  = $prevBalance + $amount;
            $newVersion  = ((int) ($status->balance_version ?? 0)) + 1;
            $reference   = 'CASHIN-' . $tab->tab_number . '-' . Str::upper(Str::random(5));
            $now         = now();

            // Direct Cage BUYIN Ledger Transaction (No float needed)
            TableLedger::create([
                'table_id'       => $tab->id,
                'tab_id'         => $tab->id,
                'txn_type'       => 'BUYIN',
                'payment_medium' => 'CASH',
                'amount'         => $amount,
                'tab_balance'    => $newBalance,
                'float_balance'  => 0.00,
                'gameday'        => $now->toDateString(),
                'processed'      => 1,
                'reference'      => $reference,
                'initiated_by'   => $currentUserId,
            ]);

            // Update TabStatus for disaster recovery & heartbeat tracking
            $status->update([
                'status'               => 'active',
                'current_session_id'   => $session->session_id,
                'current_otp'          => $otp,
                'player_name'          => $session->player_name,
                'player_phone'         => $session->player_phone,
                'player_card_id'       => $session->player_card_id,
                'session_started_at'   => $session->opened_at,
                'current_balance'      => $newBalance,
                'balance_version'      => $newVersion,
                'last_op_type'         => 'BUYIN',
                'last_op_amount'       => $amount,
                'last_op_prev_balance' => $prevBalance,
                'last_op_new_balance'  => $newBalance,
                'last_op_ref'          => $reference,
                'last_op_notes'        => 'Cage Buyin',
                'last_op_at'           => $now,
                'session_buyin'        => $session->buyin_amount,
                'shift_total_in'       => $status->shift_total_in + $amount,
                'last_synced_at'       => $now,
                'recovery_data'        => [
                    'tab_id'          => $tab->id,
                    'tab_name'        => $tab->table_name,
                    'session_id'      => $session->session_id,
                    'otp'             => $otp,
                    'player_name'     => $session->player_name,
                    'balance'         => $newBalance,
                    'balance_version' => $newVersion,
                    'last_operation'  => [
                        'type'             => 'BUYIN',
                        'amount'           => $amount,
                        'previous_balance' => $prevBalance,
                        'new_balance'      => $newBalance,
                        'reference'        => $reference,
                        'notes'            => 'Cage Buyin',
                        'timestamp'        => $now->toIso8601String(),
                    ],
                    'game_config_id'  => $tab->game_config_id,
                    'updated_at'      => $now->toIso8601String(),
                ],
            ]);

            TabBalanceLog::create([
                'tab_id'          => $tab->id,
                'session_id'      => $session->session_id,
                'balance_version' => $newVersion,
                'op_type'         => 'BUYIN',
                'amount'          => $amount,
                'prev_balance'    => $prevBalance,
                'new_balance'     => $newBalance,
                'reference'       => $reference,
                'notes'           => 'Cage Buyin',
                'metadata'        => ['initiated_by' => $currentUserId],
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Cashin processed successfully.']);
        }

        return redirect()->route('dashboard')
            ->with('success', "Cashin of \${$request->amount} processed successfully for Tab '{$tab->table_name}'.");
    }

    /**
     * Cashout from Cashier side.
     */
    public function cashout(Request $request, $id)
    {
        $tab = Tab::findOrFail($id);
        $status = $tab->statusRecord;

        if (!$status || $status->current_balance <= 0) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Tab has zero balance to cashout.'], 400);
            }
            return redirect()->route('dashboard')
                ->with('error', "Tab '{$tab->table_name}' has zero balance.");
        }

        DB::transaction(function () use ($tab, $status) {
            $currentUserId = auth()->id() ?? 1;
            $balance       = (float) $status->current_balance;
            $newVersion    = ((int) ($status->balance_version ?? 0)) + 1;
            $reference     = 'CASHOUT-' . $tab->tab_number . '-' . Str::upper(Str::random(5));
            $now           = now();

            $session = $tab->currentSession;
            if ($session) {
                $session->update([
                    'cashout_amount'  => $balance,
                    'closing_balance' => 0,
                    'closed_by'       => $currentUserId,
                    'status'          => 'completed',
                    'closed_at'       => $now,
                ]);
            }

            // Direct Cage CASHOUT Ledger Transaction
            TableLedger::create([
                'table_id'       => $tab->id,
                'tab_id'         => $tab->id,
                'txn_type'       => 'CASHOUT',
                'payment_medium' => 'CASH',
                'amount'         => $balance,
                'tab_balance'    => 0.00,
                'float_balance'  => 0.00,
                'gameday'        => $now->toDateString(),
                'processed'      => 1,
                'reference'      => $reference,
                'initiated_by'   => $currentUserId,
            ]);

            // Reset TabStatus with last_operation recorded for heartbeat & disaster recovery
            $status->update([
                'status'               => 'idle',
                'current_session_id'   => null,
                'current_otp'          => null,
                'player_name'          => null,
                'player_phone'         => null,
                'player_card_id'       => null,
                'session_started_at'   => null,
                'current_balance'      => 0.00,
                'balance_version'      => $newVersion,
                'last_op_type'         => 'CASHOUT',
                'last_op_amount'       => $balance,
                'last_op_prev_balance' => $balance,
                'last_op_new_balance'  => 0.00,
                'last_op_ref'          => $reference,
                'last_op_notes'        => 'Cage Cashout',
                'last_op_at'           => $now,
                'session_buyin'        => 0.00,
                'session_cashout'      => $balance,
                'shift_total_out'      => $status->shift_total_out + $balance,
                'last_synced_at'       => $now,
                'recovery_data'        => [
                    'tab_id'          => $tab->id,
                    'tab_name'        => $tab->table_name,
                    'balance'         => 0.00,
                    'balance_version' => $newVersion,
                    'status'          => 'idle',
                    'last_operation'  => [
                        'type'             => 'CASHOUT',
                        'amount'           => $balance,
                        'previous_balance' => $balance,
                        'new_balance'      => 0.00,
                        'reference'        => $reference,
                        'notes'            => 'Cage Cashout',
                        'timestamp'        => $now->toIso8601String(),
                    ],
                    'cleared_at'      => $now->toIso8601String(),
                ],
            ]);

            TabBalanceLog::create([
                'tab_id'          => $tab->id,
                'session_id'      => $session?->session_id,
                'balance_version' => $newVersion,
                'op_type'         => 'CASHOUT',
                'amount'          => $balance,
                'prev_balance'    => $balance,
                'new_balance'     => 0.00,
                'reference'       => $reference,
                'notes'           => 'Cage Cashout',
                'metadata'        => ['initiated_by' => $currentUserId],
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Cashout completed.']);
        }

        return redirect()->route('dashboard')
            ->with('success', "Cashout completed for Tab '{$tab->table_name}'.");
    }

    /**
     * Lock / Unlock tab from Cashier side.
     */
    public function toggleLock(Request $request, $id)
    {
        $tab = Tab::findOrFail($id);
        $newStatus = ($tab->lock_status === 'locked' || $tab->lock_status === 'break') ? 'unlocked' : 'locked';

        $tab->update([
            'lock_status' => $newStatus,
            'locked_at'   => $newStatus === 'locked' ? now() : null,
            'locked_by'   => $newStatus === 'locked' ? auth()->id() : null,
        ]);

        if ($tab->statusRecord) {
            $statusData = [
                'lock_status' => $newStatus,
                'status'      => $newStatus === 'locked' ? 'locked' : ($tab->statusRecord->current_balance > 0 ? 'active' : 'idle'),
            ];

            // If player provided OTP during lock and tab has no OTP set yet
            if ($newStatus === 'locked' && $request->filled('otp')) {
                $otp = trim($request->otp);
                $statusData['current_otp'] = $otp;
                if ($tab->currentSession) {
                    $tab->currentSession->update(['otp' => $otp]);
                }
            }

            $tab->statusRecord->update($statusData);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'     => true,
                'lock_status' => $newStatus,
                'current_otp' => $tab->statusRecord?->current_otp,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', "Tab '{$tab->table_name}' is now {$newStatus}.");
    }

    /**
     * Assign or change Game Config from Dashboard Cashier side.
     */
    public function assignConfig(Request $request, $id)
    {
        $tab = Tab::findOrFail($id);

        $request->validate([
            'game_config_id' => 'required|exists:game_configs,id',
        ]);

        $config = GameConfig::findOrFail($request->game_config_id);

        $tab->update([
            'game_config_id' => $config->id,
            'game_type_id'   => $config->game_type_id,
        ]);

        // Ensure this game config is registered in tab_game_configs
        TabGameConfig::firstOrCreate(
            [
                'tab_id'         => $tab->id,
                'game_config_id' => $config->id,
            ],
            [
                'is_active'   => 1,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Game Config '{$config->name}' assigned to Tab '{$tab->table_name}'."]);
        }

        return redirect()->route('dashboard')
            ->with('success', "Game Config '{$config->name}' assigned to Tab '{$tab->table_name}'.");
    }

    /**
     * Trigger Disaster Recovery & Session Restoration from Dashboard.
     */
    public function restore(Request $request, $id)
    {
        $tab = Tab::with('statusRecord')->findOrFail($id);
        $status = $tab->statusRecord;

        if (!$status) {
            return response()->json(['success' => false, 'message' => 'No state record found for this tab.'], 404);
        }

        $status->update([
            'last_synced_at'    => now(),
            'connection_status' => 'online',
        ]);

        return response()->json([
            'success'       => true,
            'message'       => "State restored for Tab '{$tab->table_name}'.",
            'recovery_data' => $status->recovery_data,
            'balance'       => (float) $status->current_balance,
            'otp'           => $status->current_otp,
            'player'        => $status->player_name,
        ]);
    }

    /**
     * Polling endpoint for live updates.
     */
    public function liveData()
    {
        $tabs = Tab::with('statusRecord')->get()->map(fn($t) => [
            'id'          => $t->id,
            'tab_number'  => $t->tab_number,
            'name'        => $t->table_name,
            'balance'     => $t->statusRecord ? (float)$t->statusRecord->current_balance : 0.00,
            'status'      => $t->statusRecord?->status ?? 'idle',
            'lock_status' => $t->lock_status,
            'has_session' => ($t->statusRecord && $t->statusRecord->current_balance > 0),
        ]);

        $statuses = TabStatus::all();

        return response()->json([
            'success'  => true,
            'tabs'     => $tabs,
            'kpis'     => [
                'total_in'   => (float) $statuses->sum('shift_total_in'),
                'total_out'  => (float) $statuses->sum('shift_total_out'),
                'credits'    => (float) $statuses->sum('current_balance'),
                'total_bet'  => (float) $statuses->sum('shift_total_bet'),
                'total_win'  => (float) $statuses->sum('shift_total_win'),
                'active'     => $statuses->where('status', 'active')->count(),
            ],
        ]);
    }
}
