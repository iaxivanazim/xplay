<?php

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\GameType;
use App\Models\Chip;
use App\Models\GameTableConfig;
use App\Models\BaccaratPreset;
use App\Models\AndarBaharPreset;
use App\Models\RoulettePreset;
use App\Models\PayoutRule;
use App\Models\GameTablePayoutRule;
use App\Models\TabStatus;
use App\Services\TabStatusService;
use App\Rules\PipeSeparatedNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TabController — manages the Tabs Configuration module.
 *
 * Responsibilities:
 *   1. Full CRUD for tabs, presets (AB, BAC, ROL), and payout rules.
 *   2. Tab state management (enable/disable, lock/unlock/break, MAC register).
 */
class TabController extends Controller
{
    public function __construct(private TabStatusService $statusService) {}

    private function resolvePresetModel(int $gameTypeId): string
    {
        return match (GameType::findOrFail($gameTypeId)->code) {
            'BAC'   => BaccaratPreset::class,
            'AB'    => AndarBaharPreset::class,
            'ROL'   => RoulettePreset::class,
            default => throw new \Exception("Unknown game type: " . $gameTypeId),
        };
    }

    private function validateMinMaxPairs(string $minBet, string $maxBet): void
    {
        $mins = array_map('trim', explode('|', $minBet));
        $maxs = array_map('trim', explode('|', $maxBet));

        if (count($mins) !== count($maxs)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'config.max_bet' => 'Min and Max bet must have the same number of values.'
            ]);
        }

        foreach ($mins as $i => $min) {
            if ((float) $maxs[$i] <= (float) $min) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'config.max_bet' => "Max bet value #" . ($i + 1) . " ({$maxs[$i]}) must be greater than min bet ({$min})."
                ]);
            }
        }
    }

    // ── Index ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $gameTypeFilter = $request->input('game_type');
        $statusFilter   = $request->input('status', 'all'); // all | active | inactive

        $query = GameTable::with(['gameType', 'tabStatus', 'activeSession', 'config.preset.chipPreset'])
            ->orderBy('table_name');

        if ($gameTypeFilter) {
            $query->whereHas('gameType', fn($q) => $q->where('code', $gameTypeFilter));
        }

        if ($statusFilter === 'active') {
            $query->where('status', 1);
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 0);
        }

        $tabs      = $query->get();
        $gameTypes = GameType::where('status', 1)->get();

        // Aggregate counts for header
        $totalCount  = $tabs->count();
        $activeCount = $tabs->filter(fn($t) => $t->tabStatus?->hasActiveSession())->count();

        return view('tabs.index', compact('tabs', 'gameTypes', 'totalCount', 'activeCount', 'gameTypeFilter', 'statusFilter'));
    }

    // ── Create / Store ────────────────────────────────────────────

    public function create()
    {
        $gameTypes   = GameType::where('status', 1)->get();
        $chipPresets = Chip::where('status', 1)->get();
        return view('tabs.create', compact('gameTypes', 'chipPresets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_name'          => 'required|string|max:255|unique:game_tables,table_name',
            'game_type_id'        => 'required|exists:game_types,id',
            'active_mac'          => 'nullable|string|max:255',
            'denomination'        => 'nullable|numeric|min:0.0001',
            'bet_index'           => 'nullable|integer|min:1|max:9',
            'float'               => 'nullable|numeric',
            'chip_preset_id'      => 'required|exists:chips,id',
            'config.name'         => 'required|string|max:255',
            'config.min_bet'      => ['required', new PipeSeparatedNumbers],
            'config.max_bet'      => ['required', new PipeSeparatedNumbers],
            'config.burn_card'    => 'nullable|integer|min:0',
            'config.side_min_bet' => 'nullable|numeric|min:0',
            'config.side_max_bet' => 'nullable|numeric|min:0',
            'config.roulette_type'=> 'nullable|in:european,american',
        ]);

        $this->validateMinMaxPairs(
            $request->input('config.min_bet'),
            $request->input('config.max_bet')
        );

        if ($request->filled('config.side_min_bet') && $request->filled('config.side_max_bet')) {
            if ((float)$request->input('config.side_max_bet') <= (float)$request->input('config.side_min_bet')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'config.side_max_bet' => 'Side max bet must be greater than side min bet.'
                ]);
            }
        }

        DB::transaction(function () use ($request) {
            // 1. Create GameTable (tab)
            $tab = GameTable::create([
                'table_name'   => $request->table_name,
                'game_type_id' => $request->game_type_id,
                'active_mac'   => $request->active_mac ?: null,
                'denomination' => $request->denomination ?? 1.0,
                'bet_index'    => $request->bet_index ?? 1,
                'float'        => $request->float ?? 0,
                'status'       => true,
                'lock_status'  => 'unlocked',
            ]);

            // 2. Resolve preset model
            $gameType = GameType::findOrFail($request->game_type_id);
            $presetModel = $this->resolvePresetModel($request->game_type_id);

            // 3. Create game-specific preset
            $configData = array_merge(
                $request->input('config', []),
                ['chip_preset_id' => $request->chip_preset_id]
            );

            // sync commission for baccarat
            if ($gameType->code === 'BAC') {
                if (isset($configData['baccarat_6_commission'])) {
                    $configData['commission'] = (bool)$configData['baccarat_6_commission'];
                }
            } elseif ($gameType->code === 'ROL') {
                unset($configData['burn_card']);
            }

            $preset = $presetModel::create($configData);

            // 4. Link to pivot (game_table_configs)
            GameTableConfig::create([
                'table_id'    => $tab->id,
                'preset_type' => $preset::class,
                'preset_id'   => $preset->id,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]);

            // 5. Payout rules
            $allRules = PayoutRule::where('game_type_id', $request->game_type_id)->pluck('payout_id');
            $overrides = $request->input('payout_overrides', []);

            $payoutData = $allRules->map(function ($payoutId) use ($overrides, $tab) {
                return [
                    'table_id'   => $tab->id,
                    'payout_id'  => $payoutId,
                    'is_active'  => isset($overrides[$payoutId]) ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            GameTablePayoutRule::insert($payoutData);

            $seedValues = $request->input('seed_values', []);
            foreach ($seedValues as $payoutId => $seedValue) {
                if ($seedValue === null || $seedValue === '') continue;

                GameTablePayoutRule::where('table_id', $tab->id)
                    ->where('payout_id', $payoutId)
                    ->update(['seed_value' => $seedValue]);
            }

            // 6. Initialise tab_statuses row (failure recovery baseline)
            $this->statusService->initialise($tab);
        });

        return redirect()->route('tabs.index')
            ->with('success', 'Tab configured successfully.');
    }

    // ── Edit / Update ─────────────────────────────────────────────

    public function edit(GameTable $tab)
    {
        $tab->load(['gameType', 'tabStatus', 'activeSession', 'config.preset.chipPreset', 'payoutRules.payoutRule']);
        $gameTypes   = GameType::where('status', 1)->get();
        $chipPresets = Chip::where('status', 1)->get();
        $payoutRules = PayoutRule::where('game_type_id', $tab->game_type_id)->get()->map(function ($rule) use ($tab) {
            $saved = GameTablePayoutRule::where('table_id', $tab->id)
                ->where('payout_id', $rule->payout_id)->first();
            $rule->is_active = $saved ? $saved->is_active : $rule->is_active;
            $rule->seed_value = $saved?->seed_value;
            return $rule;
        });

        return view('tabs.edit', compact('tab', 'gameTypes', 'chipPresets', 'payoutRules'));
    }

    public function update(Request $request, GameTable $tab)
    {
        // Block edit if session is active
        if ($tab->hasActiveSession()) {
            return back()->with('error', "Tab '{$tab->table_name}' has an active player session. End the session before editing.");
        }

        $request->validate([
            'table_name'          => 'required|string|max:255|unique:game_tables,table_name,' . $tab->id,
            'game_type_id'        => 'nullable|exists:game_types,id',
            'active_mac'          => 'nullable|string|max:255',
            'denomination'        => 'nullable|numeric|min:0.0001',
            'bet_index'           => 'nullable|integer|min:1|max:9',
            'float'               => 'nullable|numeric',
            'chip_preset_id'      => 'required|exists:chips,id',
            'config.name'         => 'required|string|max:255',
            'config.min_bet'      => ['required', new PipeSeparatedNumbers],
            'config.max_bet'      => ['required', new PipeSeparatedNumbers],
            'config.burn_card'    => 'nullable|integer|min:0',
            'config.side_min_bet' => 'nullable|numeric|min:0',
            'config.side_max_bet' => 'nullable|numeric|min:0',
            'config.roulette_type'=> 'nullable|in:european,american',
        ]);

        $this->validateMinMaxPairs(
            $request->input('config.min_bet'),
            $request->input('config.max_bet')
        );

        if ($request->filled('config.side_min_bet') && $request->filled('config.side_max_bet')) {
            if ((float)$request->input('config.side_max_bet') <= (float)$request->input('config.side_min_bet')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'config.side_max_bet' => 'Side max bet must be greater than side min bet.'
                ]);
            }
        }

        DB::transaction(function () use ($request, $tab) {
            // 1. Update GameTable
            $tab->update([
                'table_name'   => $request->table_name,
                'active_mac'   => $request->active_mac ?: null,
                'denomination' => $request->denomination ?? $tab->denomination,
                'bet_index'    => $request->bet_index ?? $tab->bet_index,
                'float'        => $request->float ?? $tab->float,
            ]);

            // 2. Update or create preset
            $config = $tab->config;
            $configData = array_merge(
                $request->input('config', []),
                ['chip_preset_id' => $request->chip_preset_id]
            );

            $gameType = $tab->gameType;
            if ($gameType?->code === 'BAC') {
                if (isset($configData['baccarat_6_commission'])) {
                    $configData['commission'] = (bool)$configData['baccarat_6_commission'];
                }
            } elseif ($gameType?->code === 'ROL') {
                unset($configData['burn_card']);
            }

            if ($config && $config->preset) {
                $config->preset->update($configData);
            } else {
                $presetModel = $this->resolvePresetModel($tab->game_type_id);
                $preset = $presetModel::create($configData);

                if ($config) {
                    $config->update([
                        'preset_type' => $preset::class,
                        'preset_id'   => $preset->id,
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                    ]);
                } else {
                    GameTableConfig::create([
                        'table_id'    => $tab->id,
                        'preset_type' => $preset::class,
                        'preset_id'   => $preset->id,
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                    ]);
                }
            }

            // 3. Sync payout rules
            $allRules = PayoutRule::where('game_type_id', $tab->game_type_id)->pluck('payout_id');
            $overrides = $request->input('payout_overrides', []);
            $seedValues = $request->input('seed_values', []);

            foreach ($allRules as $payoutId) {
                GameTablePayoutRule::updateOrCreate(
                    [
                        'table_id'  => $tab->id,
                        'payout_id' => $payoutId,
                    ],
                    [
                        'is_active' => isset($overrides[$payoutId]) ? 1 : 0,
                    ]
                );
            }

            foreach ($seedValues as $payoutId => $seedValue) {
                GameTablePayoutRule::where('table_id', $tab->id)
                    ->where('payout_id', $payoutId)
                    ->update([
                        'seed_value' => ($seedValue !== '' && $seedValue !== null) ? $seedValue : null
                    ]);
            }

            // 4. Update tab status if exists
            if ($tab->tabStatus) {
                $tab->tabStatus->update([
                    'active_mac'     => $tab->active_mac,
                    'last_synced_at' => now(),
                ]);
            }
        });

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$tab->table_name}' configuration updated.");
    }

    // ── Bet Index (API & helpers) ─────────────────────────────────

    public function getBetIndex($id)
    {
        $table = GameTable::findOrFail($id);
        $preset = $table->config?->preset;

        if (!$preset) {
            return response()->json([
                'success'      => false,
                'table_id'     => $table->id,
                'message'      => 'No preset configured for this tab.',
            ], 404);
        }

        $mins = explode('|', $preset->min_bet);
        $maxs = explode('|', $preset->max_bet);
        $total = count($mins);
        $currentIndex = max(1, min((int)($table->bet_index ?? 1), $total));
        $i = $currentIndex - 1;

        return response()->json([
            'success'      => true,
            'table_id'     => $table->id,
            'bet_index'    => $currentIndex,
            'total_tiers'  => $total,
            'active_range' => [
                'min' => (float)$mins[$i],
                'max' => (float)$maxs[$i],
            ],
            'all_ranges' => array_map(fn($idx) => [
                'index' => $idx + 1,
                'min'   => (float)$mins[$idx],
                'max'   => (float)$maxs[$idx],
            ], range(0, $total - 1)),
        ]);
    }

    public function setBetIndex(Request $request, $id)
    {
        $table = GameTable::findOrFail($id);
        $preset = $table->config?->preset;

        $total = $preset ? count(explode('|', $preset->min_bet)) : 1;

        $request->validate([
            'bet_index' => "required|integer|min:1|max:{$total}",
        ]);

        $table->update(['bet_index' => $request->bet_index]);

        $mins = explode('|', $preset->min_bet);
        $maxs = explode('|', $preset->max_bet);
        $i = $request->bet_index - 1;

        return response()->json([
            'success'      => true,
            'table_id'     => $table->id,
            'bet_index'    => $table->bet_index,
            'active_range' => [
                'min' => (float)$mins[$i],
                'max' => (float)$maxs[$i],
            ],
        ]);
    }

    // ── Enable / Disable ──────────────────────────────────────────

    public function enable(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot enable: tab has an active session.');
        }

        DB::transaction(function () use ($tab) {
            $tab->update(['status' => true]);
            $this->statusService->onStatusChange($tab, true);
        });

        return back()->with('success', "Tab '{$tab->table_name}' enabled.");
    }

    public function disable(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot disable: tab has an active session. Process a Cashout first.');
        }

        DB::transaction(function () use ($tab) {
            $tab->update(['status' => false]);
            $this->statusService->onStatusChange($tab, false);
        });

        return back()->with('success', "Tab '{$tab->table_name}' disabled.");
    }

    // ── Lock / Unlock / Break ─────────────────────────────────────

    public function lock(Request $request, GameTable $tab)
    {
        $lockStatus = $request->input('lock_status', 'locked'); // locked | break
        if (!in_array($lockStatus, ['locked', 'break'])) {
            return back()->with('error', 'Invalid lock status.');
        }

        DB::transaction(function () use ($tab, $lockStatus) {
            $tab->update([
                'lock_status' => $lockStatus,
                'locked_at'   => now(),
                'locked_by'   => auth()->id(),
            ]);
            $this->statusService->onLockChange($tab, $lockStatus);
        });

        $label = $lockStatus === 'break' ? 'put on break' : 'locked';
        return back()->with('success', "Tab '{$tab->table_name}' {$label}.");
    }

    public function unlock(GameTable $tab)
    {
        DB::transaction(function () use ($tab) {
            $tab->update([
                'lock_status' => 'unlocked',
                'locked_at'   => null,
                'locked_by'   => null,
            ]);
            $this->statusService->onLockChange($tab, 'unlocked');
        });

        return back()->with('success', "Tab '{$tab->table_name}' unlocked.");
    }

    // ── MAC Address Management ────────────────────────────────────

    public function registerMac(Request $request, GameTable $tab)
    {
        $request->validate([
            'mac_address' => [
                'required', 'string',
                'regex:/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/',
            ],
        ]);

        $mac = strtoupper($request->mac_address);

        // Check MAC not already bound to another tab
        $conflict = GameTable::where('active_mac', $mac)
            ->where('id', '!=', $tab->id)->first();

        if ($conflict) {
            return back()->with('error', "MAC {$mac} is already bound to tab '{$conflict->table_name}'.");
        }

        DB::transaction(function () use ($tab, $mac) {
            $tab->update(['active_mac' => $mac]);
            $this->statusService->onMacChange($tab, $mac);
        });

        return back()->with('success', "MAC {$mac} registered to tab '{$tab->table_name}'.");
    }

    public function unregisterMac(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot unregister MAC while a session is active.');
        }

        DB::transaction(function () use ($tab) {
            $tab->update(['active_mac' => null]);
            $this->statusService->onMacChange($tab, null);
        });

        return back()->with('success', "MAC unregistered from tab '{$tab->table_name}'.");
    }

    // ── Delete ────────────────────────────────────────────────────

    public function destroy(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot delete a tab with an active session.');
        }

        $name = $tab->table_name;
        $tab->delete();

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$name}' deleted.");
    }
}
