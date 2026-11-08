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

    public function bulkCreate()
    {
        $gameConfigs   = GameConfig::where('is_active', 1)->with('gameType')->get();
        $defaultConfig = GameConfig::where('is_default', 1)->first() ?? $gameConfigs->first();
        $nextNumber    = (Tab::max('tab_number') ?? 0) + 1;

        return view('tabs.bulk_create', compact('gameConfigs', 'defaultConfig', 'nextNumber'));
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'game_config_id' => 'required|exists:game_configs,id',
            'denomination'   => 'required|numeric|min:0.0001',
            'lock_status'    => 'nullable|string|in:unlocked,locked,break',
            'status'         => 'nullable|boolean',

            // Mode A (sequential generator)
            'naming_prefix'  => 'nullable|string|max:50',
            'start_number'   => 'nullable|integer|min:1',
            'quantity'       => 'nullable|integer|min:1|max:100',
            'start_ip'       => 'nullable|ip',

            // Mode B (explicit rows)
            'tabs'               => 'nullable|array|min:1',
            'tabs.*.tab_number'  => 'required_with:tabs|integer|min:1',
            'tabs.*.table_name'  => 'required_with:tabs|string|max:100',
            'tabs.*.ip_address'  => 'nullable|ip',
            'tabs.*.active_mac'  => 'nullable|string|max:50',
        ]);

        $tabItems = [];

        if ($request->has('tabs') && is_array($request->tabs) && count($request->tabs) > 0) {
            foreach ($request->tabs as $row) {
                if (empty($row['tab_number']) || empty($row['table_name'])) {
                    continue;
                }
                $tabItems[] = [
                    'tab_number' => (int) $row['tab_number'],
                    'table_name' => trim($row['table_name']),
                    'ip_address' => !empty($row['ip_address']) ? trim($row['ip_address']) : null,
                    'active_mac' => !empty($row['active_mac']) ? trim($row['active_mac']) : null,
                ];
            }
        }

        if (empty($tabItems)) {
            $prefix      = $request->input('naming_prefix', 'Tab-') ?: 'Tab-';
            $startNumber = (int) $request->input('start_number', (Tab::max('tab_number') ?? 0) + 1);
            $quantity    = (int) $request->input('quantity', 5);
            $startIp     = $request->input('start_ip');
            $startIpLong = $startIp ? ip2long($startIp) : null;

            for ($i = 0; $i < $quantity; $i++) {
                $num = $startNumber + $i;
                $tabItems[] = [
                    'tab_number' => $num,
                    'table_name' => $prefix . $num,
                    'ip_address' => ($startIpLong !== null) ? long2ip($startIpLong + $i) : null,
                    'active_mac' => null,
                ];
            }
        }

        if (empty($tabItems)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'No tabs specified to create.'], 422);
            }
            return back()->withInput()->withErrors(['tabs' => 'No tabs specified to create.']);
        }

        // 1. Validate internal batch uniqueness
        $tabNumbers = array_column($tabItems, 'tab_number');
        if (count($tabNumbers) !== count(array_unique($tabNumbers))) {
            $msg = 'Duplicate tab numbers found within the submitted batch.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors(['tabs' => $msg]);
        }

        $tableNames = array_column($tabItems, 'table_name');
        if (count($tableNames) !== count(array_unique($tableNames))) {
            $msg = 'Duplicate tab names found within the submitted batch.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors(['tabs' => $msg]);
        }

        // 2. Validate against existing database records
        $existingNumbers = Tab::whereIn('tab_number', $tabNumbers)->pluck('tab_number')->toArray();
        if (!empty($existingNumbers)) {
            $msg = 'The following tab number(s) already exist in database: ' . implode(', ', $existingNumbers) . '. Please select different numbers.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg, 'conflicts' => $existingNumbers], 409);
            }
            return back()->withInput()->withErrors(['tabs' => $msg]);
        }

        $existingNames = Tab::whereIn('table_name', $tableNames)->pluck('table_name')->toArray();
        if (!empty($existingNames)) {
            $msg = 'The following tab name(s) already exist in database: ' . implode(', ', $existingNames) . '. Please choose different names.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg, 'conflicts' => $existingNames], 409);
            }
            return back()->withInput()->withErrors(['tabs' => $msg]);
        }

        $createdTabs = DB::transaction(function () use ($request, $tabItems) {
            $primaryConfig = GameConfig::findOrFail($request->game_config_id);
            $denomination  = (float) $request->denomination;
            $lockStatus    = $request->input('lock_status', 'unlocked');
            $status        = $request->boolean('status', true);
            $currentUserId = auth()->id();
            $batchCreated  = [];

            foreach ($tabItems as $item) {
                $cleanMac = !empty($item['active_mac'])
                    ? strtoupper(str_replace('-', ':', $item['active_mac']))
                    : null;

                $tab = Tab::create([
                    'tab_number'        => $item['tab_number'],
                    'table_name'        => $item['table_name'],
                    'game_type_id'      => $primaryConfig->game_type_id,
                    'game_config_id'    => $primaryConfig->id,
                    'ip_address'        => $item['ip_address'],
                    'active_mac'        => $cleanMac,
                    'denomination'      => $denomination,
                    'status'            => $status,
                    'lock_status'       => $lockStatus,
                    'connection_status' => 'online',
                    'float'             => 0.00,
                    'bet_index'         => 1,
                    'last_seen_at'      => now(),
                ]);

                // Register primary game config in pivot
                TabGameConfig::create([
                    'tab_id'         => $tab->id,
                    'game_config_id' => $primaryConfig->id,
                    'is_active'      => 1,
                    'assigned_by'    => $currentUserId,
                    'assigned_at'    => now(),
                ]);

                // Create initial TabStatus for failure restoration
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

                $batchCreated[] = $tab;
            }

            return $batchCreated;
        });

        $count = count($createdTabs);
        $primaryConfig = GameConfig::find($request->game_config_id);
        $configName = $primaryConfig ? $primaryConfig->name : 'Basic Config';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully created {$count} tabs in bulk with basic config '{$configName}'.",
                'count'   => $count,
                'data'    => $createdTabs,
            ], 201);
        }

        $firstNum = $tabItems[0]['tab_number'];
        $lastNum  = end($tabItems)['tab_number'];

        return redirect()->route('tabs.index')
            ->with('success', "Successfully added {$count} tabs in bulk (Tab #{$firstNum} to #{$lastNum}) with basic config '{$configName}'. Each tab can now be edited individually.");
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

    public function toggleLock(Request $request, Tab $tab)
    {
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

            if ($newStatus === 'locked' && $request->filled('otp')) {
                $otp = trim($request->otp);
                $statusData['current_otp'] = $otp;
                if ($tab->currentSession) {
                    $tab->currentSession->update(['otp' => $otp]);
                }
            }

            $tab->statusRecord->update($statusData);
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
