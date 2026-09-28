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
use App\Models\TableLedger;
use App\Models\TableFloat;
use App\Rules\PipeSeparatedNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class GameTableController extends Controller
{
    private function resolvePresetModel(int $gameTypeId): string
    {
        return match (GameType::findOrFail($gameTypeId)->code) {
            'BAC' => BaccaratPreset::class,
            'AB'  => AndarBaharPreset::class,
            'ROL' => RoulettePreset::class,
            default => throw new \Exception("Unknown game type: " . $gameTypeId),
        };
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 1);
        $sortBy = $request->input('sort_by', 'created_at');
        $order  = $request->input('order', 'desc');
        $macFilter = $request->input('mac_filter', 'all');

        // Validate sort parameters
        $allowedSorts = ['table_name', 'created_at', 'id'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'created_at';
        if (!in_array($order, ['asc', 'desc'])) $order = 'desc';

        $query = GameTable::with(['gameType', 'config.preset.chipPreset', 'payoutRules.payoutRule', 'currentFloat'])
            ->where('status', $status);


        if ($macFilter === 'bound') {
            $query->whereNotNull('active_mac');
        } elseif ($macFilter === 'unbound') {
            $query->whereNull('active_mac');
        }

        // Primary sort: tables with an open float (closed_at IS NULL) come first.
        // Secondary sort: user-selected column / direction.
        $query->orderByRaw('
            CASE WHEN EXISTS (
                SELECT 1 FROM table_floats
                WHERE table_floats.table_id = game_tables.id
                  AND table_floats.closed_at IS NULL
            ) THEN 0 ELSE 1 END ASC
        ')->orderBy($sortBy, $order);

        if ($request->search) {
            $query->where('table_name', 'like', "%{$request->search}%");
        }

        $tables = $query->paginate(10)->appends($request->only(['status', 'search', 'sort_by', 'order', 'mac_filter']));

        return view('game_tables.index', compact('tables', 'status', 'sortBy', 'order', 'macFilter'));
    }

    public function create()
    {
        $gameTypes   = GameType::where('status', 1)->get();
        $chipPresets = Chip::where('status', 1)->get();
        return view('game_tables.create', compact('gameTypes', 'chipPresets'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'table_name'     => 'required|string|max:255|unique:game_tables,table_name',
            'game_type_id'   => 'required|exists:game_types,id',
            'active_mac'     => 'nullable|string|max:255',
            'float'          => 'nullable|numeric',
            'chip_preset_id' => 'required|exists:chips,id',
            'config.name'    => 'required|string|max:255',
            'config.min_bet' => ['required', new PipeSeparatedNumbers],
            'config.max_bet' => ['required', new PipeSeparatedNumbers],
            'config.burn_card' => 'nullable|integer|min:0',
        ]);

        $this->validateMinMaxPairs(
            $request->input('config.min_bet'),
            $request->input('config.max_bet')
        );

        DB::transaction(function () use ($request) {

            // 1. Create game table
            $table = GameTable::create([
                'table_name'   => $request->table_name,
                'game_type_id' => $request->game_type_id,
                'active_mac'   => $request->active_mac,
                'float'        => $request->float,
                'status'       => 1,
            ]);


            // 2. Resolve preset model
            $presetModel = $this->resolvePresetModel($request->game_type_id);

            // 3. Create game-specific preset
            $configData = array_merge(
                $request->input('config', []),
                ['chip_preset_id' => $request->chip_preset_id]
            );

            // sync commission from single dropdown
            if (isset($configData['baccarat_6_commission'])) {
                $configData['commission'] = $configData['baccarat_6_commission'];
            }

            $preset = $presetModel::create($configData);

            // 4. Link to pivot
            GameTableConfig::create([
                'table_id'    => $table->id,
                'preset_type' => $preset::class,
                'preset_id'   => $preset->id,
                'assigned_by' => auth()->guard('web')->user()->id,
                'assigned_at' => now(),
            ]);

            // ── Sync payout rules ──────────────────────────────────────────
            $allRules = PayoutRule::where('game_type_id', $request->game_type_id)
                ->pluck('payout_id');

            $overrides = $request->input('payout_overrides', []); // checked = active

            $payoutData = $allRules->map(function ($payoutId) use ($overrides, $table) {
                return [
                    'table_id'   => $table->id,
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

                GameTablePayoutRule::where('table_id', $table->id)
                    ->where('payout_id', $payoutId)
                    ->update(['seed_value' => $seedValue]);
            }
        });

        return redirect()->route('game_tables.index')
            ->with('success', 'Game table configured successfully.');
    }

    public function edit(GameTable $gameTable)
    {
        if ($gameTable->isFloatOpen()) {
            return redirect()->route('game_tables.index')
                ->with('error', "Table '{$gameTable->table_name}' is currently OPEN. You must close the table float before editing its configuration.");
        }

        $gameTable->load(['gameType', 'config.preset.chipPreset']);
        $gameTypes    = GameType::where('status', 1)->get();
        $chipPresets  = Chip::where('status', 1)->get();

        // Get global payout rules merged with this table's saved overrides
        $payoutRules = PayoutRule::where('game_type_id', $gameTable->game_type_id)
            ->get()
            ->map(function ($rule) use ($gameTable) {
                // Override is_active with table-specific saved value if it exists
                $saved = GameTablePayoutRule::where('table_id', $gameTable->id)
                    ->where('payout_id', $rule->payout_id)
                    ->first();
                $rule->is_active = $saved ? $saved->is_active : $rule->is_active;
                return $rule;
            });

        return view('game_tables.edit', compact(
            'gameTable',
            'gameTypes',
            'chipPresets',
            'payoutRules'
        ));
    }


    public function update(Request $request, GameTable $gameTable)
    {
        if ($gameTable->isFloatOpen()) {
            return redirect()->route('game_tables.index')
                ->with('error', "Table '{$gameTable->table_name}' is currently OPEN. You must close the table float before updating its configuration.");
        }

        $request->validate([
            'table_name'     => 'required|string|max:255|unique:game_tables,table_name,' . $gameTable->id,
            'game_type_id'   => 'required|exists:game_types,id',
            'active_mac'     => 'nullable|string|max:255',
            'float'          => 'nullable|numeric',
            'chip_preset_id' => 'required|exists:chips,id',
            'config.name'    => 'required|string|max:255',
            'config.min_bet' => ['required', new PipeSeparatedNumbers],
            'config.max_bet' => ['required', new PipeSeparatedNumbers],
            'config.burn_card' => 'nullable|integer|min:0',
        ]);

        $this->validateMinMaxPairs(
            $request->input('config.min_bet'),
            $request->input('config.max_bet')
        );
        DB::transaction(function () use ($request, $gameTable) {

            // 1. Update game table
            $gameTable->update([
                'table_name' => $request->table_name,
                'active_mac' => $request->active_mac,
                'float'      => $request->float,
            ]);


            // 2. Update preset
            $config  = $gameTable->config;
            $preset  = $config->preset;
            $configData = $request->input('config', []);

            if (isset($configData['baccarat_6_commission'])) {
                $configData['commission'] = $configData['baccarat_6_commission'];
            }

            $preset->update(array_merge(
                $configData,
                ['chip_preset_id' => $request->chip_preset_id]
            ));

            // ── Sync payout rules ──────────────────────────────────────────
            $allRules = PayoutRule::where('game_type_id', $gameTable->game_type_id)
                ->pluck('payout_id');

            $overrides = $request->input('payout_overrides', []);

            foreach ($allRules as $payoutId) {
                GameTablePayoutRule::updateOrCreate(
                    [
                        'table_id'  => $gameTable->id,
                        'payout_id' => $payoutId,
                    ],
                    [
                        'is_active' => isset($overrides[$payoutId]) ? 1 : 0,
                    ]
                );
            }
            $seedValues = $request->input('seed_values', []);

            foreach ($seedValues as $payoutId => $seedValue) {
                GameTablePayoutRule::where('table_id', $gameTable->id)
                    ->where('payout_id', $payoutId)
                    ->update([
                        'seed_value' => ($seedValue !== '' && $seedValue !== null)
                            ? $seedValue
                            : null
                    ]);
            }
        });

        return redirect()->route('game_tables.index')
            ->with('success', 'Configuration updated successfully.');
    }

    public function deactivate(GameTable $gameTable)
    {
        if ($gameTable->isFloatOpen()) {
            return redirect()->route('game_tables.index')
                ->with('error', "Table '{$gameTable->table_name}' is currently OPEN. You must close the table float before deactivating it.");
        }

        $gameTable->update(['status' => 0]);

        return redirect()
            ->route('game_tables.index', ['status' => 1])
            ->with('success', "Table '{$gameTable->table_name}' deactivated successfully.");
    }

    public function restore(GameTable $gameTable)
    {
        $gameTable->update(['status' => 1]);

        return redirect()
            ->route('game_tables.index', ['status' => 1])
            ->with('success', "Table '{$gameTable->table_name}' restored successfully.");
    }

    //Helper method for Min Max Validation
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

    public function getBetIndex($id)
    {
        $table = GameTable::findOrFail($id);
        $preset = $table->config?->preset;

        $mins = $preset ? explode('|', $preset->min_bet) : [];
        $maxs = $preset ? explode('|', $preset->max_bet) : [];
        $total = count($mins);
        $index = $table->bet_index ?? 1;

        return response()->json([
            'table_id'    => $table->id,
            'bet_index'   => $index,
            'total_pairs' => $total,
            'active_range' => [
                'min' => isset($mins[$index - 1]) ? (float)$mins[$index - 1] : null,
                'max' => isset($maxs[$index - 1]) ? (float)$maxs[$index - 1] : null,
            ],
            'all_ranges' => array_map(fn($i) => [
                'index' => $i + 1,
                'min'   => (float)$mins[$i],
                'max'   => (float)$maxs[$i],
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

    // ══════════════════════════════════════════════════════
    // API — Fetch all game tables
    // GET /api/v1/game-tables
    // ══════════════════════════════════════════════════════
    public function apiIndex(Request $request)
    {
        try {
            $tables = GameTable::with([
                'gameType',
                'config.preset.chipPreset',
                'payoutRules.payoutRule'
            ])
                ->where('status', 1)
                ->latest()
                ->get()
                ->map(fn($table) => $this->formatTableResponse($table));

            return response()->json([
                'success' => true,
                'count'   => $tables->count(),
                'data'    => $tables
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch game tables.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // API — Fetch single game table by ID
    // GET /api/v1/game-tables/{id}
    // ══════════════════════════════════════════════════════
    public function apiShow($id)
    {
        try {
            $table = GameTable::with([
                'gameType',
                'config.preset.chipPreset',
                'payoutRules.payoutRule'
            ])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $this->formatTableResponse($table)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Game table with ID {$id} not found.",
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch game table.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // API — Active tables, by MAC, configuration
    // ══════════════════════════════════════════════════════

    public function apiActive()
    {
        $tables = GameTable::with([
            'gameType',
            'config.preset.chipPreset',
            'payoutRules.payoutRule'
        ])
            ->where('status', 1)
            ->latest()
            ->get()
            ->map(fn($table) => $this->formatTableResponse($table));

        return response()->json([
            'success' => true,
            'count'   => $tables->count(),
            'data'    => $tables
        ], 200);
    }

    public function apiByMac($mac)
    {
        $table = GameTable::with([
            'gameType',
            'config.preset.chipPreset',
            'payoutRules.payoutRule'
        ])
            ->where('active_mac', strtoupper($mac))
            ->where('status', 1)
            ->first();

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or inactive table for MAC: ' . $mac,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatTableResponse($table)
        ], 200);
    }

    public function apiConfiguration($id)
    {
        $table = GameTable::with([
            'gameType',
            'config.preset.chipPreset',
            'payoutRules.payoutRule'
        ])
            ->findOrFail($id);

        return response()->json([
            'success'       => true,
            'configuration' => $this->formatTableResponse($table)
        ], 200);
    }

    private function formatTableResponse(GameTable $table): array
    {
        $config = $table->config;
        $preset = $config?->preset;
        $chip   = $preset?->chipPreset;

        return [
            'table_id'        => $table->id,
            'table_name'      => $table->table_name,
            'status'          => (bool) $table->status,
            'lock_status'     => $table->lock_status ?? 'unlocked',
            'active_mac'      => $table->active_mac,
            'denomination'    => (float) ($table->denomination ?? 1.0),
            'bet_index'       => (int) ($table->bet_index ?? 1),
            'active_bet_range'=> $table->active_bet_range,
            'float'           => (float) $table->float,
            'game_type'       => $table->gameType ? [
                'id'          => $table->gameType->id,
                'name'        => $table->gameType->name,
                'code'        => $table->gameType->code,
                'description' => $table->gameType->description,
            ] : null,
            'config'          => $preset ? array_merge(
                [
                    'preset_id'   => $preset->id,
                    'preset_name' => $preset->name,
                    'min_bet'     => $this->parsePipeValues($preset->min_bet),
                    'max_bet'     => $this->parsePipeValues($preset->max_bet),
                    'burn_card'   => $preset->burn_card ?? null,
                ],
                $this->formatPresetFields($preset)
            ) : null,
            'chip_preset'     => $chip ? [
                'id'          => $chip->id,
                'preset_name' => $chip->preset_name,
                'base_value'  => (float) $chip->base_value,
                'chips'       => [
                    ['position' => 1, 'value' => (float) $chip->chip_1_value],
                    ['position' => 2, 'value' => (float) $chip->chip_2_value],
                    ['position' => 3, 'value' => (float) $chip->chip_3_value],
                    ['position' => 4, 'value' => (float) $chip->chip_4_value],
                    ['position' => 5, 'value' => (float) $chip->chip_5_value],
                ],
            ] : null,
            'payout_rules'    => $table->payoutRules
                ->filter(fn($r) => $r->payoutRule !== null)
                ->map(function ($r) use ($preset) {
                    $multiplier = $r->payoutRule->payout_multiplier
                        ? (float) $r->payoutRule->payout_multiplier
                        : null;

                    if ($preset instanceof BaccaratPreset) {
                        if (strtoupper($r->payoutRule->bet_position ?? '') === 'B') {
                            $multiplier = $preset->getBankerMultiplier();
                        }
                        if (strtoupper($r->payoutRule->bet_position ?? '') === 'B6' && $r->is_active) {
                            $multiplier = $preset->getBaccarat6Multiplier();
                        }
                    }

                    return [
                        'payout_id'         => $r->payoutRule->payout_id,
                        'bet_name'          => $r->payoutRule->bet_name,
                        'bet_position'      => $r->payoutRule->bet_position,
                        'payout_multiplier' => $multiplier,
                        'is_jackpot'        => (bool) $r->payoutRule->is_jackpot,
                        'seed_value'        => ($r->payoutRule->is_jackpot && $r->is_active)
                            ? (float) $r->seed_value
                            : null,
                        'is_active'         => (bool) $r->is_active,
                    ];
                })
                ->values(),
            'created_at'      => $table->created_at?->toISOString(),
            'updated_at'      => $table->updated_at?->toISOString(),
        ];
    }

    private function formatPresetFields($preset): array
    {
        return match (true) {
            $preset instanceof BaccaratPreset => [
                'side_min_bet'         => (float) $preset->side_min_bet,
                'side_max_bet'         => (float) $preset->side_max_bet,
                'commission'           => (bool) $preset->commission,
                'banker_multiplier'    => $preset->getBankerMultiplier(),
                'baccarat_6_commission'=> (bool) $preset->baccarat_6_commission,
                'baccarat_6_multiplier'=> $preset->getBaccarat6Multiplier(),
            ],
            $preset instanceof AndarBaharPreset => [
                'andar_min'            => isset($preset->andar_min) ? (float) $preset->andar_min : null,
                'andar_max'            => isset($preset->andar_max) ? (float) $preset->andar_max : null,
                'bahar_min'            => isset($preset->bahar_min) ? (float) $preset->bahar_min : null,
                'bahar_max'            => isset($preset->bahar_max) ? (float) $preset->bahar_max : null,
                'side_min_bet'         => isset($preset->side_min_bet) ? (float) $preset->side_min_bet : null,
                'side_max_bet'         => isset($preset->side_max_bet) ? (float) $preset->side_max_bet : null,
            ],
            $preset instanceof RoulettePreset => [
                'roulette_type'        => $preset->roulette_type,
                'side_min_bet'         => (float) $preset->side_min_bet,
                'side_max_bet'         => (float) $preset->side_max_bet,
            ],
            default => []
        };
    }

    private function parsePipeValues(?string $value): array
    {
        if (!$value) return [];
        return array_map(
            fn($v) => (float) trim($v),
            explode('|', $value)
        );
    }

    public function registerMac(Request $request, $id)
    {
        // Force JSON responses regardless of the client's Accept header
        $request->headers->set('Accept', 'application/json');

        $request->validate([
            'mac_address' => [
                'required',
                'string',
                'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/' // valid MAC format
            ],
        ]);

        try {
            $table      = GameTable::findOrFail($id);
            $incomingMac = strtoupper($request->mac_address);

            // ── Table is inactive ────────────────────────────────────────
            if ($table->status !== true) {
                return response()->json([
                    'success' => false,
                    'code'    => 'TABLE_INACTIVE',
                    'message' => "Table '{$table->table_name}' is inactive and cannot be registered.",
                ], 422);
            }

            // ── No MAC registered yet → register it ─────────────────────
            if (is_null($table->active_mac)) {
                $table->update(['active_mac' => $incomingMac]);

                return response()->json([
                    'success' => true,
                    'code'    => 'MAC_REGISTERED',
                    'message' => "MAC address registered successfully for table '{$table->table_name}'.",
                    'data'    => [
                        'table_id'   => $table->id,
                        'table_name' => $table->table_name,
                        'active_mac' => $incomingMac,
                        'registered_at' => now()->toISOString(),
                    ],
                ], 200);
            }

            $registeredMac = strtoupper($table->active_mac);

            // ── Same MAC → already bound to this device ──────────────────
            if ($registeredMac === $incomingMac) {
                return response()->json([
                    'success' => true,
                    'code'    => 'MAC_ALREADY_REGISTERED',
                    'message' => "This device is already registered to table '{$table->table_name}'.",
                    'data'    => [
                        'table_id'   => $table->id,
                        'table_name' => $table->table_name,
                        'active_mac' => $registeredMac,
                    ],
                ], 200);
            }

            // ── Different MAC → table bound to another device ────────────
            return response()->json([
                'success' => false,
                'code'    => 'MAC_CONFLICT',
                'message' => "Table '{$table->table_name}' is already bound to a different device. Contact administrator to reassign.",
                'data'    => [
                    'table_id'      => $table->id,
                    'table_name'    => $table->table_name,
                    'registered_mac' => $registeredMac,
                    'incoming_mac'  => $incomingMac,
                ],
            ], 409); // 409 Conflict

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'TABLE_NOT_FOUND',
                'message' => "Game table with ID {$id} not found.",
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'SERVER_ERROR',
                'message' => 'Failed to process MAC registration.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function unregisterMac(GameTable $gameTable)
    {
        $old = $gameTable->active_mac;
        $gameTable->update(['active_mac' => null]);

        return redirect()
            ->route('game_tables.index')
            ->with('success', "MAC address ({$old}) unregistered from '{$gameTable->table_name}'. Device can now be reassigned.");
    }

    public function currentFloat(Request $request, $id)
    {
        $request->validate([
            'gameday' => 'required|date_format:Y-m-d',
        ]);

        try {
            $table   = GameTable::findOrFail($id);
            $gameday = $request->gameday;

            // 1. Check active session for this gameday
            $session = TableFloat::where('table_id', $id)
                ->where('gameday', $gameday)
                ->latest('float_id')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'code'    => 'NO_SESSION',
                    'message' => "No session found for table '{$table->table_name}' on gameday {$gameday}.",
                    'data'    => null,
                ], 404);
            }

            // 2. Get latest ledger entry for current float balance
            //    If no transactions yet, float_open is the current float
            $lastTxn = TableLedger::where('table_id', $id)
                ->where('gameday', $gameday)
                ->latest('txn_id')
                ->first();

            $currentFloat = $lastTxn
                ? (float) $lastTxn->float_balance
                : (float) $session->float_open;

            // 3. Build movement summary for context
            $txns      = TableLedger::where('table_id', $id)
                ->where('gameday', $gameday)
                ->get();

            $totalIn  = (float) $txns->whereIn('txn_type', ['FILL', 'CREDIT', 'BUYIN'])
                ->sum('amount');
            $totalOut = (float) $txns->whereIn('txn_type', ['DROP', 'CASHOUT'])
                ->sum('amount');

            return response()->json([
                'success' => true,
                'code'    => 'FLOAT_FETCHED',
                'data'    => [
                    'table_id'      => $table->id,
                    'table_name'    => $table->table_name,
                    'gameday'       => $gameday,
                    'session_status' => $session->status === 1 ? 'open' : 'closed',

                    'float' => [
                        'open'    => (float) $session->float_open,
                        'current' => $currentFloat,
                        'close'   => $session->float_close
                            ? (float) $session->float_close
                            : null,
                        'movement' => [
                            'total_in'  => $totalIn,
                            'total_out' => $totalOut,
                            'net'       => round($totalIn - $totalOut, 2),
                        ],
                    ],

                    'last_txn' => $lastTxn ? [
                        'txn_id'   => $lastTxn->txn_id,
                        'txn_type' => $lastTxn->txn_type,
                        'amount'   => (float) $lastTxn->amount,
                        'at'       => $lastTxn->created_at?->toISOString(),
                    ] : null,
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'TABLE_NOT_FOUND',
                'message' => "Game table with ID {$id} not found.",
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 'SERVER_ERROR',
                'message' => 'Failed to fetch float balance.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
