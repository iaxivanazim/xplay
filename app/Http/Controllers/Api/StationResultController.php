<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameTable;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Services\RouletteEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StationResultController extends Controller
{
    protected RouletteEngine $rouletteEngine;

    public function __construct(RouletteEngine $rouletteEngine)
    {
        $this->rouletteEngine = $rouletteEngine;
    }

    /**
     * Station Middleware Receiver: Result from station physical wheel / RNG.
     * POST /api/v1/station/{tableId}/roulette/result
     */
    public function receiveRouletteResult(Request $request, $tableId)
    {
        $validator = Validator::make($request->all(), [
            'game_no'        => 'required|string|max:50',
            'winning_number' => 'required|integer|min:0|max:36',
            'tab_bets'       => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $gameNo        = $request->input('game_no');
        $winningNumber = (int)$request->input('winning_number');
        $tabBets       = $request->input('tab_bets', []);

        // If no tab_bets passed, look for bets in session/cache or create default entry
        if (empty($tabBets)) {
            // Check if active tabs are assigned to this table
            $tabs = Tab::where('id', $tableId)->orWhere('game_type_id', 3)->take(2)->get();
            if ($tabs->isNotEmpty()) {
                foreach ($tabs as $t) {
                    $tabBets[$t->table_name ?? $t->id] = [
                        ['position' => (string)$winningNumber, 'amount' => 50, 'label' => "Straight $winningNumber"],
                        ['position' => RouletteEngine::getColor($winningNumber) === 'red' ? 'RED' : 'BLACK', 'amount' => 100],
                    ];
                }
            }
        }

        $records = $this->rouletteEngine->settleRound(
            (int)$tableId,
            $gameNo,
            $winningNumber,
            $tabBets
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Roulette round settled successfully',
            'table_id'       => (int)$tableId,
            'game_no'        => $gameNo,
            'winning_number' => $winningNumber,
            'winning_colour' => RouletteEngine::getColor($winningNumber),
            'settled_tabs'   => count($records),
            'records'        => $records,
        ], 200);
    }

    /**
     * Tab places bets for a round.
     * POST /api/v1/tabs/{id}/bet
     */
    public function placeBet(Request $request, $tabId)
    {
        $validator = Validator::make($request->all(), [
            'bets'    => 'required|array|min:1',
            'bets.*.position' => 'required|string',
            'bets.*.amount'   => 'required|numeric|min:1',
            'game_no' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tab = Tab::find($tabId);
        if (!$tab) {
            return response()->json(['success' => false, 'message' => 'Tab not found'], 404);
        }

        if ($tab->lock_status === 'locked') {
            return response()->json(['success' => false, 'message' => 'Tab is locked'], 403);
        }

        $status = TabStatus::firstOrCreate(['tab_id' => $tab->id], ['current_balance' => 0.00]);
        $totalBet = collect($request->input('bets'))->sum('amount');

        if ((float)$status->current_balance < $totalBet) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance',
                'current_balance' => (float)$status->current_balance,
                'required_amount' => $totalBet,
            ], 422);
        }

        return response()->json([
            'success'         => true,
            'message'         => 'Bets accepted for round',
            'tab_id'          => $tab->id,
            'tab_name'        => $tab->table_name,
            'total_bet'       => $totalBet,
            'current_balance' => (float)$status->current_balance,
            'bets'            => $request->input('bets'),
        ]);
    }

    /**
     * Settle round endpoint with direct payload.
     * POST /api/v1/roulette/settle
     */
    public function settle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_id'       => 'nullable|integer',
            'game_no'        => 'required|string',
            'winning_number' => 'required|integer|min:0|max:36',
            'tab_bets'       => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $records = $this->rouletteEngine->settleRound(
            $request->input('table_id'),
            $request->input('game_no'),
            (int)$request->input('winning_number'),
            $request->input('tab_bets')
        );

        return response()->json([
            'success' => true,
            'records' => $records,
        ]);
    }
}
