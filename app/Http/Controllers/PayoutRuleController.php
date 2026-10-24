<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GameType;
use App\Models\PayoutRule;

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
        PayoutRule::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Rule Added'
        ]);
    }


    public function update(Request $request, $id)
    {
        $rule = PayoutRule::findOrFail($id);

        $rule->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Rule Updated'
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

            ]);
        }

        return response()->json([

            'success' => true,
            'data' => $rule

        ]);
    }
}
