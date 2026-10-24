<?php

namespace App\Http\Controllers;

use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\TabGameConfig;
use App\Models\GameConfig;
use App\Models\GameType;
use App\Models\TableLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TabController extends Controller
{
    public function index(Request $request)
    {
        $query = Tab::with([
            'gameConfig.gameType',
            'gameConfigs.gameType',
            'statusRecord',
            'currentSession',
            'gameType',
        ]);

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('table_name', 'like', "%{$term}%")
                    ->orWhere('tab_number', $term)
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhere('active_mac', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('lock_status')) {
            $query->where('lock_status', $request->lock_status);
        }

        $tabs = $query->orderBy('tab_number', 'asc')->get();

        // Calculate Cage KPIs
        $statuses = TabStatus::all();

        $totalIn      = (float) $statuses->sum('shift_total_in');
        $totalOut     = (float) $statuses->sum('shift_total_out');
        $difference   = $totalIn - $totalOut;
        $totalBet     = (float) $statuses->sum('shift_total_bet');
        $totalWin     = (float) $statuses->sum('shift_total_win');
        $ftIn         = (float) $statuses->sum(fn($s) => $s->shift_total_in * 0.30);
        $ftOut        = $totalOut;
        $billIn       = (float) $statuses->sum(fn($s) => $s->shift_total_in * 0.70);
        $credits      = (float) $statuses->sum('current_balance');
        $machinesCount= $tabs->count();
        $activeCount  = $statuses->where('status', 'active')->count();

        $gameConfigs = GameConfig::where('is_active', 1)->get();

        return view('tabs.index', compact(
            'tabs',
            'totalIn',
            'totalOut',
            'difference',
            'totalBet',
            'totalWin',
            'ftIn',
            'ftOut',
            'billIn',
            'credits',
            'machinesCount',
            'activeCount',
            'gameConfigs'
        ));
    }

    public function create()
    {
        $gameConfigs = GameConfig::where('is_active', 1)->with('gameType')->get();
        $gameTypes   = GameType::where('status', 1)->get();
        $nextNumber  = (Tab::max('tab_number') ?? 0) + 1;

        return view('tabs.create', compact('gameConfigs', 'gameTypes', 'nextNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tab_number'     => 'required|integer|min:1|unique:tabs,tab_number',
            'table_name'     => 'required|string|max:100|unique:tabs,table_name',
            'ip_address'     => 'nullable|ip',
            'active_mac'     => 'nullable|string|max:50',
            'denomination'   => 'required|numeric|min:0.0001',
            'game_config_id' => 'required|exists:game_configs,id',
            'game_configs'   => 'nullable|array',
            'game_configs.*' => 'exists:game_configs,id',
            'status'         => 'nullable|boolean',
            'lock_status'    => 'nullable|string|in:unlocked,locked,break',
        ]);

        DB::transaction(function () use ($request) {
            $primaryConfig = GameConfig::findOrFail($request->game_config_id);

            $tab = Tab::create([
                'tab_number'        => $request->tab_number,
                'table_name'        => $request->table_name,
                'game_type_id'      => $primaryConfig->game_type_id,
                'game_config_id'    => $primaryConfig->id,
                'ip_address'        => $request->ip_address,
                'active_mac'        => $request->active_mac ? strtoupper($request->active_mac) : null,
                'denomination'      => $request->denomination,
                'status'            => $request->boolean('status', true),
                'lock_status'       => $request->lock_status ?? 'unlocked',
                'connection_status' => 'online',
                'float'             => 0.00,
                'bet_index'         => 1,
                'last_seen_at'      => now(),
            ]);

            // Sync assigned game configs (including the primary one)
            $assignedConfigIds = $request->input('game_configs', []);
            if (!in_array($primaryConfig->id, $assignedConfigIds)) {
                $assignedConfigIds[] = $primaryConfig->id;
            }

            $currentUserId = auth()->id();
            foreach ($assignedConfigIds as $cfgId) {
                TabGameConfig::create([
                    'tab_id'         => $tab->id,
                    'game_config_id' => $cfgId,
                    'is_active'      => 1,
                    'assigned_by'    => $currentUserId,
                    'assigned_at'    => now(),
                ]);
            }

            // Create initial TabStatus for failure management
            TabStatus::create([
                'tab_id'            => $tab->id,
                'status'            => 'idle',
                'lock_status'       => $tab->lock_status,
                'current_balance'   => 0.00,
                'session_buyin'     => 0.00,
                'session_cashout'   => 0.00,
                'shift_total_in'    => 0.00,
                'shift_total_out'   => 0.00,
                'shift_total_bet'   => 0.00,
                'shift_total_win'   => 0.00,
                'shift_games_count' => 0,
                'shift_last_bet'    => 0.00,
                'connection_status' => 'online',
                'active_mac'        => $tab->active_mac,
                'last_synced_at'    => now(),
                'recovery_data'     => [
                    'tab_id'    => $tab->id,
                    'tab_name'  => $tab->table_name,
                    'balance'   => 0,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        });

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$request->table_name}' configured successfully.");
    }

    public function edit(Tab $tab)
    {
        $tab->load(['gameConfigs', 'statusRecord', 'currentSession']);
        $gameConfigs = GameConfig::where('is_active', 1)->with('gameType')->get();
        $gameTypes   = GameType::where('status', 1)->get();
        $assignedConfigIds = $tab->gameConfigs->pluck('id')->toArray();

        return view('tabs.edit', compact('tab', 'gameConfigs', 'gameTypes', 'assignedConfigIds'));
    }

    public function update(Request $request, Tab $tab)
    {
        $request->validate([
            'tab_number'     => 'required|integer|min:1|unique:tabs,tab_number,' . $tab->id,
            'table_name'     => 'required|string|max:100|unique:tabs,table_name,' . $tab->id,
            'ip_address'     => 'nullable|ip',
            'active_mac'     => 'nullable|string|max:50',
            'denomination'   => 'required|numeric|min:0.0001',
            'game_config_id' => 'required|exists:game_configs,id',
            'game_configs'   => 'nullable|array',
            'game_configs.*' => 'exists:game_configs,id',
            'status'         => 'nullable|boolean',
            'lock_status'    => 'nullable|string|in:unlocked,locked,break',
        ]);

        DB::transaction(function () use ($request, $tab) {
            $primaryConfig = GameConfig::findOrFail($request->game_config_id);

            $tab->update([
                'tab_number'     => $request->tab_number,
                'table_name'     => $request->table_name,
                'game_type_id'   => $primaryConfig->game_type_id,
                'game_config_id' => $primaryConfig->id,
                'ip_address'     => $request->ip_address,
                'active_mac'     => $request->active_mac ? strtoupper($request->active_mac) : null,
                'denomination'   => $request->denomination,
                'status'         => $request->boolean('status', true),
                'lock_status'    => $request->lock_status ?? $tab->lock_status,
            ]);

            // Sync assigned game configs
            $assignedConfigIds = $request->input('game_configs', []);
            if (!in_array($primaryConfig->id, $assignedConfigIds)) {
                $assignedConfigIds[] = $primaryConfig->id;
            }

            $currentUserId = auth()->id();
            TabGameConfig::where('tab_id', $tab->id)->delete();
            foreach ($assignedConfigIds as $cfgId) {
                TabGameConfig::create([
                    'tab_id'         => $tab->id,
                    'game_config_id' => $cfgId,
                    'is_active'      => 1,
                    'assigned_by'    => $currentUserId,
                    'assigned_at'    => now(),
                ]);
            }

            // Sync lock_status to TabStatus
            if ($tab->statusRecord) {
                $tab->statusRecord->update([
                    'lock_status' => $tab->lock_status,
                    'active_mac'  => $tab->active_mac,
                ]);
            }
        });

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$tab->table_name}' updated successfully.");
    }

    public function destroy(Tab $tab)
    {
        if ($tab->statusRecord && $tab->statusRecord->current_balance > 0) {
            return redirect()->route('tabs.index')
                ->with('error', "Cannot delete Tab '{$tab->table_name}' because it has active balance of \${$tab->statusRecord->current_balance}. Cashout first.");
        }

        $tab->delete();

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$tab->table_name}' deleted successfully.");
    }

    public function toggleLock(Tab $tab)
    {
        $newStatus = ($tab->lock_status === 'locked' || $tab->lock_status === 'break') ? 'unlocked' : 'locked';

        $tab->update([
            'lock_status' => $newStatus,
            'locked_at'   => $newStatus === 'locked' ? now() : null,
            'locked_by'   => $newStatus === 'locked' ? auth()->id() : null,
        ]);

        if ($tab->statusRecord) {
            $tab->statusRecord->update([
                'lock_status' => $newStatus,
                'status'      => $newStatus === 'locked' ? 'locked' : ($tab->statusRecord->current_balance > 0 ? 'active' : 'idle'),
            ]);
        }

        return redirect()->back()
            ->with('success', "Tab '{$tab->table_name}' is now {$newStatus}.");
    }

    public function deactivate(Tab $tab)
    {
        $tab->update(['status' => 0]);
        if ($tab->statusRecord) {
            $tab->statusRecord->update(['status' => 'disabled']);
        }

        return redirect()->back()
            ->with('success', "Tab '{$tab->table_name}' disabled successfully.");
    }

    public function restore(Tab $tab)
    {
        $tab->update(['status' => 1]);
        if ($tab->statusRecord) {
            $tab->statusRecord->update(['status' => 'idle']);
        }

        return redirect()->back()
            ->with('success', "Tab '{$tab->table_name}' enabled successfully.");
    }

    public function unregisterMac(Tab $tab)
    {
        $old = $tab->active_mac;
        $tab->update(['active_mac' => null]);
        if ($tab->statusRecord) {
            $tab->statusRecord->update(['active_mac' => null]);
        }

        return redirect()->back()
            ->with('success', "MAC address ({$old}) unregistered from Tab '{$tab->table_name}'.");
    }

    public function assignConfigs(Request $request, Tab $tab)
    {
        $request->validate([
            'game_config_id' => 'required|exists:game_configs,id',
            'game_configs'   => 'nullable|array',
            'game_configs.*' => 'exists:game_configs,id',
        ]);

        DB::transaction(function () use ($request, $tab) {
            $primaryConfig = GameConfig::findOrFail($request->game_config_id);

            $tab->update([
                'game_config_id' => $primaryConfig->id,
                'game_type_id'   => $primaryConfig->game_type_id,
            ]);

            $assignedConfigIds = $request->input('game_configs', []);
            if (!in_array($primaryConfig->id, $assignedConfigIds)) {
                $assignedConfigIds[] = $primaryConfig->id;
            }

            TabGameConfig::where('tab_id', $tab->id)->delete();
            foreach ($assignedConfigIds as $cfgId) {
                TabGameConfig::create([
                    'tab_id'         => $tab->id,
                    'game_config_id' => $cfgId,
                    'is_active'      => 1,
                    'assigned_by'    => auth()->id(),
                    'assigned_at'    => now(),
                ]);
            }
        });

        return redirect()->back()
            ->with('success', "Game configurations assigned to Tab '{$tab->table_name}'.");
    }

    // ── Cashin: Assign Player & Add Cash ─────────────────────────
    public function cashin(Request $request, Tab $tab)
    {
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

            // Existing or new session
            $session = $tab->currentSession;
            if (!$session) {
                $otp = str_pad(strval(rand(100000, 999999)), 6, '0', STR_PAD_LEFT);
                $session = TabSession::create([
                    'tab_id'          => $tab->id,
                    'otp'             => $otp,
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

            $newBalance = $status->current_balance + $amount;

            // Direct Cage BUYIN Ledger Transaction (No table float required)
            TableLedger::create([
                'table_id'       => $tab->id,
                'tab_id'         => $tab->id,
                'txn_type'       => 'BUYIN',
                'payment_medium' => 'CASH',
                'amount'         => $amount,
                'tab_balance'    => $newBalance,
                'float_balance'  => 0,
                'gameday'        => now()->toDateString(),
                'processed'      => 1,
                'reference'      => 'CASHIN-S' . $session->session_id . '-' . Str::upper(Str::random(5)),
                'initiated_by'   => $currentUserId,
            ]);

            // Update TabStatus for failure restoration
            $status->update([
                'status'             => 'active',
                'current_session_id' => $session->session_id,
                'current_otp'        => $otp,
                'player_name'        => $session->player_name,
                'player_phone'       => $session->player_phone,
                'player_card_id'     => $session->player_card_id,
                'session_started_at' => $session->opened_at,
                'current_balance'    => $newBalance,
                'session_buyin'      => $session->buyin_amount,
                'shift_total_in'     => $status->shift_total_in + $amount,
                'last_synced_at'     => now(),
                'recovery_data'      => [
                    'tab_id'         => $tab->id,
                    'tab_name'       => $tab->table_name,
                    'session_id'     => $session->session_id,
                    'otp'            => $otp,
                    'player_name'    => $session->player_name,
                    'balance'        => $newBalance,
                    'game_config_id' => $tab->game_config_id,
                    'updated_at'     => now()->toIso8601String(),
                ],
            ]);
        });

        return redirect()->back()
            ->with('success', "Cashin of \${$request->amount} processed successfully for Tab '{$tab->table_name}'.");
    }

    // ── Cashout: Redeem Balance & Close Session ───────────────────
    public function cashout(Request $request, Tab $tab)
    {
        $status = $tab->statusRecord;
        if (!$status || $status->current_balance <= 0) {
            return redirect()->back()
                ->with('error', "Tab '{$tab->table_name}' has zero balance to cashout.");
        }

        DB::transaction(function () use ($tab, $status) {
            $currentUserId = auth()->id() ?? 1;
            $balance = (float) $status->current_balance;

            $session = $tab->currentSession;
            if ($session) {
                $session->update([
                    'cashout_amount'  => $balance,
                    'closing_balance' => 0,
                    'closed_by'       => $currentUserId,
                    'status'          => 'completed',
                    'closed_at'       => now(),
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
                'gameday'        => now()->toDateString(),
                'processed'      => 1,
                'reference'      => 'CASHOUT-T' . $tab->tab_number . '-' . Str::upper(Str::random(5)),
                'initiated_by'   => $currentUserId,
            ]);

            // Reset TabStatus
            $status->update([
                'status'             => 'idle',
                'current_session_id' => null,
                'current_otp'        => null,
                'player_name'        => null,
                'player_phone'       => null,
                'player_card_id'     => null,
                'session_started_at' => null,
                'current_balance'    => 0.00,
                'session_buyin'      => 0.00,
                'session_cashout'    => $balance,
                'shift_total_out'    => $status->shift_total_out + $balance,
                'last_synced_at'     => now(),
                'recovery_data'      => [
                    'tab_id'         => $tab->id,
                    'tab_name'       => $tab->table_name,
                    'balance'        => 0.00,
                    'status'         => 'idle',
                    'cleared_at'     => now()->toIso8601String(),
                ],
            ]);
        });

        return redirect()->back()
            ->with('success', "Cashout of \${$status->current_balance} completed for Tab '{$tab->table_name}'.");
    }

    // ── State Recovery / Failure Restoration Endpoint ────────────
    public function restoreState(Tab $tab)
    {
        $status = $tab->statusRecord;
        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'No state found for this tab.',
            ], 404);
        }

        return response()->json([
            'success'       => true,
            'tab_id'        => $tab->id,
            'tab_number'    => $tab->tab_number,
            'table_name'    => $tab->table_name,
            'balance'       => $status->current_balance,
            'otp'           => $status->current_otp,
            'player'        => $status->player_name,
            'status'        => $status->status,
            'lock_status'   => $status->lock_status,
            'recovery_data' => $status->recovery_data,
            'last_synced'   => $status->last_synced_at?->toIso8601String(),
        ]);
    }
}
