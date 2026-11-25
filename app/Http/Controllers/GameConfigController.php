<?php

namespace App\Http\Controllers;

use App\Models\GameConfig;
use App\Models\GameConfigPayoutRule;
use App\Models\GameType;
use App\Models\PayoutRule;
use App\Rules\PipeSeparatedNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameConfigController extends Controller
{
    public function index(Request $request)
    {
        $query = GameConfig::with(['gameType', 'payoutRules.payoutRule', 'tabs'])
            ->withCount('tabs');

        if ($request->filled('game_type_id')) {
            $query->where('game_type_id', $request->game_type_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $configs = $query->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->appends($request->all());

        $gameTypes = GameType::where('status', 1)->get();

        return view('game_configs.index', compact('configs', 'gameTypes'));
    }

    public function create()
    {
        $gameTypes = GameType::where('status', 1)->get();
        return view('game_configs.create', compact('gameTypes'));
    }

    public function store(Request $request)
    {
        $this->validateConfigRequest($request);

        DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                GameConfig::where('game_type_id', $request->game_type_id)
                    ->update(['is_default' => 0]);
            }

            $config = GameConfig::create($this->extractConfigAttributes($request));
            $this->syncPayoutRules($config, $request);
        });

        return redirect()->route('game_configs.index')
            ->with('success', 'Game Configuration created successfully.');
    }

    public function edit(GameConfig $gameConfig)
    {
        $gameConfig->load(['gameType', 'payoutRules.payoutRule']);
        $gameTypes = GameType::where('status', 1)->get();

        // Payout rules for this game type
        $payoutRules = PayoutRule::where('game_type_id', $gameConfig->game_type_id)->get();
        $savedRules = $gameConfig->payoutRules->keyBy('payout_id');

        return view('game_configs.edit', compact('gameConfig', 'gameTypes', 'payoutRules', 'savedRules'));
    }

    public function update(Request $request, GameConfig $gameConfig)
    {
        $this->validateConfigRequest($request, $gameConfig);

        DB::transaction(function () use ($request, $gameConfig) {
            if ($request->boolean('is_default')) {
                GameConfig::where('game_type_id', $gameConfig->game_type_id)
                    ->where('id', '!=', $gameConfig->id)
                    ->update(['is_default' => 0]);
            }

            $gameConfig->update($this->extractConfigAttributes($request, $gameConfig));
            $this->syncPayoutRules($gameConfig, $request);
        });

        return redirect()->route('game_configs.index')
            ->with('success', 'Game Configuration updated successfully.');
    }

    private function validateConfigRequest(Request $request, ?GameConfig $gameConfig = null): void
    {
        $nameRule = $gameConfig
            ? 'required|string|max:255|unique:game_configs,name,' . $gameConfig->id
            : 'required|string|max:255|unique:game_configs,name';

        $rules = [
            'name'                   => $nameRule,
            'device_ip'              => 'nullable|string|max:45',
            'min_bet'                => ['required', new PipeSeparatedNumbers],
            'max_bet'                => ['required', new PipeSeparatedNumbers],
            'side_min_bet'           => 'nullable|numeric|min:0',
            'side_max_bet'           => 'nullable|numeric|min:0',
            'burn_card'              => 'nullable|integer|min:0',
            'roulette_type'          => 'nullable|string|in:european,american,french',
            'commission'             => 'nullable|numeric|min:0|max:1',
            'baccarat_6_commission'  => 'nullable|boolean',
            'tie_min'                => 'nullable|numeric|min:0',
            'tie_max'                => 'nullable|numeric|min:0',
            'pair_min'               => 'nullable|numeric|min:0',
            'pair_max'               => 'nullable|numeric|min:0',
            'super6_min'             => 'nullable|numeric|min:0',
            'super6_max'             => 'nullable|numeric|min:0',
            'andar_min'              => 'nullable|numeric|min:0',
            'andar_max'              => 'nullable|numeric|min:0',
            'bahar_min'              => 'nullable|numeric|min:0',
            'bahar_max'              => 'nullable|numeric|min:0',
            'is_default'             => 'nullable|boolean',
            'is_active'              => 'nullable|boolean',
            'payout_rules'           => 'nullable|array',
            'payout_multipliers'     => 'nullable|array',
            'payout_multipliers.*'   => 'nullable|numeric|min:0',
            'payout_rule_mins'       => 'nullable|array',
            'payout_rule_mins.*'     => ['nullable', new PipeSeparatedNumbers],
            'payout_rule_maxs'       => 'nullable|array',
            'payout_rule_maxs.*'     => ['nullable', new PipeSeparatedNumbers],
        ];

        if (!$gameConfig) {
            $rules['game_type_id'] = 'required|exists:game_types,id';
        }

        $request->validate($rules, [], [
            'payout_multipliers.*' => 'payout rule multiplier',
            'payout_rule_mins.*'   => 'payout rule min',
            'payout_rule_maxs.*'   => 'payout rule max',
        ]);

        $this->validateMinMaxPairs($request->min_bet, $request->max_bet);
    }

    private function extractConfigAttributes(Request $request, ?GameConfig $existing = null): array
    {
        $attributes = [
            'name'                  => $request->name,
            'device_ip'             => $request->device_ip,
            'min_bet'               => $request->min_bet,
            'max_bet'               => $request->max_bet,
            'side_min_bet'          => $request->side_min_bet,
            'side_max_bet'          => $request->side_max_bet,
            'burn_card'             => $request->burn_card ?? 0,
            'roulette_type'         => $request->roulette_type ?? 'european',
            'baccarat_6_commission' => $request->boolean('baccarat_6_commission'),
            'commission'            => $request->commission ?? 0.05,
            'tie_min'               => $request->tie_min,
            'tie_max'               => $request->tie_max,
            'pair_min'              => $request->pair_min,
            'pair_max'              => $request->pair_max,
            'super6_min'            => $request->super6_min,
            'super6_max'            => $request->super6_max,
            'andar_min'             => $request->andar_min,
            'andar_max'             => $request->andar_max,
            'bahar_min'             => $request->bahar_min,
            'bahar_max'             => $request->bahar_max,
            'is_active'             => $request->boolean('is_active', true),
            'is_default'            => $request->boolean('is_default', false),
        ];

        if (!$existing) {
            $attributes['game_type_id'] = $request->game_type_id;
        }

        return $attributes;
    }

    private function syncPayoutRules(GameConfig $config, Request $request): void
    {
        $allRules    = PayoutRule::where('game_type_id', $config->game_type_id)->get();
        $activeRules = $request->input('payout_rules', []);
        $multipliers = $request->input('payout_multipliers', []);
        $ruleMins    = $request->input('payout_rule_mins', []);
        $ruleMaxs    = $request->input('payout_rule_maxs', []);

        foreach ($allRules as $rule) {
            // Replace edited min and max directly in payout_rules so all game configs fetch the latest values
            $ruleUpdates = [];
            if (array_key_exists($rule->payout_id, $ruleMins)) {
                $ruleUpdates['min'] = $this->normalizePipeValue($ruleMins[$rule->payout_id]);
            }
            if (array_key_exists($rule->payout_id, $ruleMaxs)) {
                $ruleUpdates['max'] = $this->normalizePipeValue($ruleMaxs[$rule->payout_id]);
            }
            if (!empty($ruleUpdates)) {
                $rule->update($ruleUpdates);
            }

            $mult = array_key_exists($rule->payout_id, $multipliers)
                && $multipliers[$rule->payout_id] !== null
                && $multipliers[$rule->payout_id] !== ''
                ? $multipliers[$rule->payout_id]
                : $rule->payout_multiplier;

            GameConfigPayoutRule::updateOrCreate(
                [
                    'game_config_id' => $config->id,
                    'payout_id'      => $rule->payout_id,
                ],
                [
                    'is_active'         => isset($activeRules[$rule->payout_id]) ? 1 : 0,
                    'payout_multiplier' => $mult,
                ]
            );
        }
    }

    private function normalizePipeValue(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $parts = array_map('trim', explode('|', (string) $value));
        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));

        return empty($parts) ? null : implode('|', $parts);
    }

    public function destroy(GameConfig $gameConfig)
    {
        if ($gameConfig->tabs()->count() > 0) {
            return redirect()->route('game_configs.index')
                ->with('error', "Cannot delete configuration '{$gameConfig->name}' because it is assigned to active tabs.");
        }

        $gameConfig->delete();

        return redirect()->route('game_configs.index')
            ->with('success', 'Game Configuration deleted successfully.');
    }

    public function setDefault(GameConfig $gameConfig)
    {
        GameConfig::where('game_type_id', $gameConfig->game_type_id)
            ->update(['is_default' => 0]);

        $gameConfig->update(['is_default' => 1]);

        return redirect()->route('game_configs.index')
            ->with('success', "Configuration '{$gameConfig->name}' marked as default for {$gameConfig->gameType?->name}.");
    }

    public function toggleActive(GameConfig $gameConfig)
    {
        $gameConfig->update(['is_active' => !$gameConfig->is_active]);

        $statusStr = $gameConfig->is_active ? 'activated' : 'deactivated';
        return redirect()->route('game_configs.index')
            ->with('success', "Configuration '{$gameConfig->name}' {$statusStr}.");
    }

    public function getPayoutRules($gameTypeId)
    {
        $rules = PayoutRule::where('game_type_id', $gameTypeId)->get();
        return response()->json($rules);
    }

    private function validateMinMaxPairs(string $minBet, string $maxBet): void
    {
        $mins = array_map('trim', explode('|', $minBet));
        $maxs = array_map('trim', explode('|', $maxBet));

        if (count($mins) !== count($maxs)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'max_bet' => 'Min and Max bet must have the same number of pipe-separated values.'
            ]);
        }

        foreach ($mins as $i => $min) {
            if ((float) $maxs[$i] <= (float) $min) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'max_bet' => "Max bet value #" . ($i + 1) . " ({$maxs[$i]}) must be greater than min bet ({$min})."
                ]);
            }
        }
    }
}
