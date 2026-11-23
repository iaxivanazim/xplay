<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GameType;
use App\Models\PayoutRule;
use App\Rules\PipeSeparatedNumbers;

class PayoutRuleController extends Controller
{
    public function index()
    {
        $gameTypes = GameType::all();

        return view('payout_rules.index', compact('gameTypes'));
    }


    public function fetchByGameType($id)
    {
        return PayoutRule::where('game_type_id', $id)
            ->orderBy('bet_position')
            ->get();
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_type_id'      => 'required|exists:game_types,id',
            'bet_name'          => 'required|string|max:255',
            'bet_position'      => 'nullable|string|max:50',
            'payout_multiplier' => 'nullable|numeric|min:0',
            'min'               => ['nullable', new PipeSeparatedNumbers],
            'max'               => ['nullable', new PipeSeparatedNumbers],
            'is_active'         => 'nullable|boolean',
            'is_jackpot'        => 'nullable|boolean',
        ]);

        $rule = PayoutRule::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rule Added',
            'data'    => $rule
        ]);
    }


    public function update(Request $request, $id)
    {
        $rule = PayoutRule::findOrFail($id);

        $validated = $request->validate([
            'game_type_id'      => 'sometimes|exists:game_types,id',
            'bet_name'          => 'sometimes|string|max:255',
            'bet_position'      => 'nullable|string|max:50',
            'payout_multiplier' => 'nullable|numeric|min:0',
            'min'               => ['nullable', new PipeSeparatedNumbers],
            'max'               => ['nullable', new PipeSeparatedNumbers],
            'is_active'         => 'sometimes|boolean',
            'is_jackpot'        => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rule Updated',
            'data'    => $rule
        ]);
    }


    public function destroy($id)
    {
        PayoutRule::destroy($id);

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

    public function apiByGameType($id)
    {

        $rules = PayoutRule::where('game_type_id', $id)
            ->where('is_active', 1)
            ->orderBy('bet_position')
            ->get();


        return response()->json([

            'success' => true,

            'count' => $rules->count(),

            'data' => $rules

        ]);
    }

    public function apiShow($id)
    {

        $rule = PayoutRule::find($id);

        if (!$rule) {

            return response()->json([

                'success' => false,
                'message' => 'Rule not found'

            ], 404);
        }

        return response()->json([

            'success' => true,
            'data' => $rule

        ]);
    }
}
