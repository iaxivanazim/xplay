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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TabController extends Controller
{
    public function index(Request $request)
    {
        // Automatically synchronize connection_status for tabs whose heartbeats or hardware registration have lapsed
        Tab::syncStaleConnections();

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
                $rawMac = !empty($row['active_mac']) ? trim((string)$row['active_mac']) : null;
                $cleanMac = !empty($rawMac) ? strtoupper(str_replace('-', ':', $rawMac)) : null;

                $tabItems[] = [
                    'tab_number' => (int) $row['tab_number'],
                    'table_name' => trim($row['table_name']),
                    'ip_address' => !empty($row['ip_address']) ? trim($row['ip_address']) : null,
                    'active_mac' => $cleanMac,
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
            $msg = 'No tabs specified to create.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->with('error', $msg)->withErrors(['tabs' => $msg]);
        }

        // 1. Validate internal batch uniqueness for Tab Numbers
        $tabNumbers = array_column($tabItems, 'tab_number');
        if (count($tabNumbers) !== count(array_unique($tabNumbers))) {
            $counts = array_count_values($tabNumbers);
            $duplicates = array_keys(array_filter($counts, fn($c) => $c > 1));
            $msg = 'Duplicate tab number(s) found within the submitted batch: #' . implode(', #', $duplicates) . '. Each tab must have a unique number.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'code' => 'DUPLICATE_TAB_NUMBER', 'message' => $msg, 'conflicts' => $duplicates], 422);
            }
            return back()->withInput()->with('error', $msg)->withErrors(['tabs' => $msg]);
        }

        // 2. Validate internal batch uniqueness for Tab Names (case-insensitive)
        $tableNames = array_column($tabItems, 'table_name');
        $lowerNames = array_map('strtolower', $tableNames);
        if (count($lowerNames) !== count(array_unique($lowerNames))) {
            $counts = array_count_values($lowerNames);
            $duplicates = array_keys(array_filter($counts, fn($c) => $c > 1));
            $msg = 'Duplicate tab name(s) found within the submitted batch: "' . implode('", "', $duplicates) . '". Each tab must have a unique name.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'code' => 'DUPLICATE_TAB_NAME', 'message' => $msg, 'conflicts' => $duplicates], 422);
            }
            return back()->withInput()->with('error', $msg)->withErrors(['tabs' => $msg]);
        }

        // 3. Validate internal batch uniqueness for MAC Addresses
        $batchMacMap = [];
        foreach ($tabItems as $item) {
            if (!empty($item['active_mac'])) {
                $batchMacMap[$item['active_mac']][] = "Tab #{$item['tab_number']} ({$item['table_name']})";
            }
        }

        $duplicateBatchMacs = [];
        foreach ($batchMacMap as $mac => $assignedTabs) {
            if (count($assignedTabs) > 1) {
                $duplicateBatchMacs[] = "'{$mac}' assigned to " . implode(' and ', $assignedTabs);
            }
        }

        if (!empty($duplicateBatchMacs)) {
            $msg = 'Duplicate MAC address found within the submitted batch: ' . implode('; ', $duplicateBatchMacs) . '. Each tablet must have a unique physical MAC address or leave it blank.';
            if ($request->wantsJson()) {
                return response()->json([
                    'success'   => false,
                    'code'      => 'DUPLICATE_MAC_IN_BATCH',
                    'message'   => $msg,
                    'conflicts' => array_keys(array_filter($batchMacMap, fn($t) => count($t) > 1)),
                ], 422);
            }
            return back()->withInput()->with('error', $msg)->withErrors(['active_mac' => $msg]);
        }

        // 4. Validate against existing database Tab Numbers
        $existingNumbers = Tab::whereIn('tab_number', $tabNumbers)->pluck('tab_number')->toArray();
        if (!empty($existingNumbers)) {
            $msg = 'The following tab number(s) already exist in database: #' . implode(', #', $existingNumbers) . '. Please select different numbers.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'code' => 'TAB_NUMBER_ALREADY_EXISTS', 'message' => $msg, 'conflicts' => $existingNumbers], 409);
            }
            return back()->withInput()->with('error', $msg)->withErrors(['tabs' => $msg]);
        }

        // 5. Validate against existing database Tab Names
        $existingNames = Tab::whereIn('table_name', $tableNames)->pluck('table_name')->toArray();
        if (!empty($existingNames)) {
            $msg = 'The following tab name(s) already exist in database: "' . implode('", "', $existingNames) . '". Please choose different names.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'code' => 'TAB_NAME_ALREADY_EXISTS', 'message' => $msg, 'conflicts' => $existingNames], 409);
            }
            return back()->withInput()->with('error', $msg)->withErrors(['tabs' => $msg]);
        }

        // 6. Validate against existing database MAC Addresses
        $submittedMacs = array_values(array_filter(array_column($tabItems, 'active_mac')));
        if (!empty($submittedMacs)) {
            $existingMacTabs = Tab::whereIn('active_mac', $submittedMacs)
                ->get(['id', 'tab_number', 'table_name', 'active_mac']);

            if ($existingMacTabs->isNotEmpty()) {
                $conflictDetails = $existingMacTabs->map(function ($tab) {
                    return "'{$tab->active_mac}' (already assigned to Tab #{$tab->tab_number} - {$tab->table_name})";
                })->implode(', ');

                $msg = "The following MAC address(es) already exist in the database: {$conflictDetails}. Each physical tablet must have a unique MAC address. Please use different MAC addresses or leave blank.";
                if ($request->wantsJson()) {
                    return response()->json([
                        'success'   => false,
                        'code'      => 'MAC_ALREADY_EXISTS',
                        'message'   => $msg,
                        'conflicts' => $existingMacTabs->pluck('active_mac')->toArray(),
                    ], 409);
                }
                return back()->withInput()->with('error', $msg)->withErrors(['active_mac' => $msg]);
            }
        }

        // 7. Atomic batch creation with database exception safety
        try {
            $createdTabs = DB::transaction(function () use ($request, $tabItems) {
                $primaryConfig = GameConfig::findOrFail($request->game_config_id);
                $denomination  = (float) $request->denomination;
                $lockStatus    = $request->input('lock_status', 'unlocked');
                $status        = $request->boolean('status', true);
                $currentUserId = auth()->id();
                $batchCreated  = [];

                foreach ($tabItems as $item) {
                    $tab = Tab::create([
                        'tab_number'        => $item['tab_number'],
                        'table_name'        => $item['table_name'],
                        'game_type_id'      => $primaryConfig->game_type_id,
                        'game_config_id'    => $primaryConfig->id,
                        'ip_address'        => $item['ip_address'],
                        'active_mac'        => $item['active_mac'],
                        'denomination'      => $denomination,
                        'status'            => $status,
                        'lock_status'       => $lockStatus,
                        'connection_status' => 'offline',
                        'float'             => 0.00,
                        'bet_index'         => 1,
                        'last_seen_at'      => null,
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
                        'status'            => ($status ? 'idle' : 'disabled'),
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
                        'connection_status' => 'offline',
                        'active_mac'        => $tab->active_mac,
                        'last_synced_at'    => null,
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
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Tab bulkStore QueryException: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            $rawMsg = $e->getMessage();
            $errorMsg = 'A database integrity error occurred while saving tabs.';

            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                preg_match("/Duplicate entry '([^']+)'/", $rawMsg, $matches);
                $dup = $matches[1] ?? 'specified value';

                if (str_contains($rawMsg, 'active_mac') || str_contains($rawMsg, 'game_tables_active_mac_unique') || str_contains($rawMsg, 'tabs_active_mac_unique')) {
                    $errorMsg = "Duplicate MAC address error: The MAC address '{$dup}' is already assigned to another tab. Each tab must have a unique MAC address.";
                } elseif (str_contains($rawMsg, 'table_name') || str_contains($rawMsg, 'game_tables_table_name_unique') || str_contains($rawMsg, 'tabs_table_name_unique')) {
                    $errorMsg = "Duplicate tab name error: A tab named '{$dup}' already exists in the system. Please use unique tab names.";
                } elseif (str_contains($rawMsg, 'tab_number')) {
                    $errorMsg = "Duplicate tab number error: Tab #{$dup} already exists in the system. Please choose unique tab numbers.";
                } else {
                    $errorMsg = "A duplicate entry violation occurred ('{$dup}'). Please ensure all tab numbers, names, and MAC addresses are unique.";
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'code'    => 'DATABASE_INTEGRITY_ERROR',
                    'message' => $errorMsg,
                ], 422);
            }

            return back()->withInput()->with('error', $errorMsg)->withErrors(['active_mac' => $errorMsg]);
        } catch (\Throwable $e) {
            Log::error('Tab bulkStore Throwable: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            $errorMsg = 'An unexpected error occurred while creating tabs: ' . $e->getMessage();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                ], 500);
            }

            return back()->withInput()->with('error', $errorMsg)->withErrors(['tabs' => $errorMsg]);
        }

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
        // Normalize MAC if present
        if ($request->filled('active_mac')) {
            $request->merge([
                'active_mac' => strtoupper(str_replace('-', ':', trim($request->active_mac))),
            ]);
        }

        $request->validate([
            'tab_number'     => 'required|integer|min:1|unique:tabs,tab_number',
            'table_name'     => 'required|string|max:100|unique:tabs,table_name',
            'ip_address'     => 'nullable|ip',
            'active_mac'     => 'nullable|string|max:50|unique:tabs,active_mac',
            'denomination'   => 'required|numeric|min:0.0001',
            'game_config_id' => 'required|exists:game_configs,id',
            'game_configs'   => 'nullable|array',
            'game_configs.*' => 'exists:game_configs,id',
            'status'         => 'nullable|boolean',
            'lock_status'    => 'nullable|string|in:unlocked,locked,break',
        ], [
            'active_mac.unique' => 'The MAC address :input is already assigned to another tab.',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $primaryConfig = GameConfig::findOrFail($request->game_config_id);

                $tab = Tab::create([
                    'tab_number'        => $request->tab_number,
                    'table_name'        => $request->table_name,
                    'game_type_id'      => $primaryConfig->game_type_id,
                    'game_config_id'    => $primaryConfig->id,
                    'ip_address'        => $request->ip_address,
                    'active_mac'        => $request->active_mac,
                    'denomination'      => $request->denomination,
                    'status'            => $request->boolean('status', true),
                    'lock_status'       => $request->lock_status ?? 'unlocked',
                    'connection_status' => 'offline',
                    'float'             => 0.00,
                    'bet_index'         => 1,
                    'last_seen_at'      => null,
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
                    'status'            => $request->boolean('status', true) ? 'idle' : 'disabled',
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
                    'connection_status' => 'offline',
                    'active_mac'        => $tab->active_mac,
                    'last_synced_at'    => null,
                    'recovery_data'     => [
                        'tab_id'    => $tab->id,
                        'tab_name'  => $tab->table_name,
                        'balance'   => 0,
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Tab store QueryException: ' . $e->getMessage(), ['exception' => $e]);
            $rawMsg = $e->getMessage();
            $errorMsg = 'A database error occurred while creating the tab.';
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                if (str_contains($rawMsg, 'active_mac')) {
                    $errorMsg = "The MAC address '{$request->active_mac}' is already registered to another tab.";
                } elseif (str_contains($rawMsg, 'table_name')) {
                    $errorMsg = "The tab name '{$request->table_name}' is already in use.";
                } else {
                    $errorMsg = 'A duplicate entry constraint error occurred.';
                }
            }
            return back()->withInput()->with('error', $errorMsg)->withErrors(['active_mac' => $errorMsg]);
        } catch (\Throwable $e) {
            Log::error('Tab store Throwable: ' . $e->getMessage(), ['exception' => $e]);
            return back()->withInput()->with('error', 'Unexpected error: ' . $e->getMessage());
        }

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
        // Normalize MAC if present
        if ($request->filled('active_mac')) {
            $request->merge([
                'active_mac' => strtoupper(str_replace('-', ':', trim($request->active_mac))),
            ]);
        }

        $request->validate([
            'tab_number'     => 'required|integer|min:1|unique:tabs,tab_number,' . $tab->id,
            'table_name'     => 'required|string|max:100|unique:tabs,table_name,' . $tab->id,
            'ip_address'     => 'nullable|ip',
            'active_mac'     => 'nullable|string|max:50|unique:tabs,active_mac,' . $tab->id,
            'denomination'   => 'required|numeric|min:0.0001',
            'game_config_id' => 'required|exists:game_configs,id',
            'game_configs'   => 'nullable|array',
            'game_configs.*' => 'exists:game_configs,id',
            'status'         => 'nullable|boolean',
            'lock_status'    => 'nullable|string|in:unlocked,locked,break',
        ], [
            'active_mac.unique' => 'The MAC address :input is already assigned to another tab.',
        ]);

        try {
            DB::transaction(function () use ($request, $tab) {
                $primaryConfig = GameConfig::findOrFail($request->game_config_id);

                // 'E' state: false when checkbox is unchecked, true when checked
                $newStatus = $request->boolean('status');

                // 'A' state: must have both IP and MAC registered AND active heartbeat within threshold
                $hasHardware = !empty($request->ip_address) && !empty($request->active_mac);
                $isOnline = $hasHardware
                    && $tab->last_seen_at
                    && $tab->last_seen_at->gte(now()->subSeconds(Tab::HEARTBEAT_TIMEOUT_SECONDS));
                $connStatus = $isOnline ? 'online' : 'offline';

                $tab->update([
                    'tab_number'        => $request->tab_number,
                    'table_name'        => $request->table_name,
                    'game_type_id'      => $primaryConfig->game_type_id,
                    'game_config_id'    => $primaryConfig->id,
                    'ip_address'        => $request->ip_address,
                    'active_mac'        => $request->active_mac,
                    'denomination'      => $request->denomination,
                    'status'            => $newStatus,
                    'lock_status'       => $request->lock_status ?? $tab->lock_status,
                    'connection_status' => $connStatus,
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

                // Sync status, lock_status, and connection_status to TabStatus
                if ($tab->statusRecord) {
                    $statusRecordUpdates = [
                        'lock_status'       => $tab->lock_status,
                        'active_mac'        => $tab->active_mac,
                        'connection_status' => $connStatus,
                    ];
                    if (!$newStatus) {
                        $statusRecordUpdates['status'] = 'disabled';
                    } elseif ($tab->statusRecord->status === 'disabled') {
                        $statusRecordUpdates['status'] = ((float)$tab->statusRecord->current_balance > 0) ? 'active' : 'idle';
                    }
                    $tab->statusRecord->update($statusRecordUpdates);
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Tab update QueryException: ' . $e->getMessage(), ['exception' => $e]);
            $rawMsg = $e->getMessage();
            $errorMsg = 'A database error occurred while updating the tab.';
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                if (str_contains($rawMsg, 'active_mac')) {
                    $errorMsg = "The MAC address '{$request->active_mac}' is already registered to another tab.";
                } elseif (str_contains($rawMsg, 'table_name')) {
                    $errorMsg = "The tab name '{$request->table_name}' is already in use.";
                } else {
                    $errorMsg = 'A duplicate entry constraint error occurred.';
                }
            }
            return back()->withInput()->with('error', $errorMsg)->withErrors(['active_mac' => $errorMsg]);
        } catch (\Throwable $e) {
            Log::error('Tab update Throwable: ' . $e->getMessage(), ['exception' => $e]);
            return back()->withInput()->with('error', 'Unexpected error: ' . $e->getMessage());
        }

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
        $tab->update([
            'active_mac'        => null,
            'connection_status' => 'offline',
        ]);
        if ($tab->statusRecord) {
            $tab->statusRecord->update([
                'active_mac'        => null,
                'connection_status' => 'offline',
            ]);
        }

        return redirect()->back()
            ->with('success', "MAC address ({$old}) unregistered from Tab '{$tab->table_name}'. Tab marked offline.");
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
