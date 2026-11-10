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

    /**
     * Batch Station Telemetry API:
     * Pit Station / Table Middleware aggregates and pushes hardware & reachability
     * telemetry for all physical tablets in its pit in a single atomic request.
     *
     * POST /api/v1/station/{tableId}/telemetry
     * POST /api/v1/station/telemetry
     * POST /api/v1/station/tabs/telemetry
     */
    public function syncTelemetry(Request $request, $tableId = null)
    {
        $validator = Validator::make($request->all(), [
            'station_id' => 'nullable',
            'tabs'       => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $stationId = $tableId ?: $request->input('station_id');
        $tabItems = $request->input('tabs', []);

        $processed = [];
        $onlineCount = 0;
        $offlineCount = 0;

        foreach ($tabItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Find Tab by id, tab_id, tab_number, or mac
            $tab = null;
            if (!empty($item['tab_id'])) {
                $tab = Tab::with('statusRecord')->find($item['tab_id']);
            } elseif (!empty($item['id'])) {
                $tab = Tab::with('statusRecord')->find($item['id']);
            } elseif (isset($item['tab_number'])) {
                $tab = Tab::with('statusRecord')->where('tab_number', $item['tab_number'])->first();
            } elseif (!empty($item['mac']) || !empty($item['active_mac'])) {
                $rawMac = $item['mac'] ?? $item['active_mac'];
                $cleanMac = strtoupper(str_replace('-', ':', trim($rawMac)));
                $tab = Tab::with('statusRecord')->where('active_mac', $cleanMac)->first();
            }

            if (!$tab) {
                $processed[] = [
                    'identifier' => $item['tab_id'] ?? $item['id'] ?? $item['tab_number'] ?? $item['mac'] ?? 'unknown',
                    'status'     => 'not_found',
                    'message'    => 'Tab not found in database',
                ];
                continue;
            }

            // Optional auto-registration of IP / MAC if not already stored
            if (!empty($item['ip_address']) && empty($tab->ip_address)) {
                $tab->ip_address = $item['ip_address'];
            }
            if ((!empty($item['mac']) || !empty($item['active_mac'])) && empty($tab->active_mac)) {
                $tab->active_mac = strtoupper(str_replace('-', ':', trim($item['mac'] ?? $item['active_mac'])));
            }

            // Tablet reachability reported by station middleware
            $isConnected = true;
            if (isset($item['is_connected'])) {
                $isConnected = (bool) $item['is_connected'];
            } elseif (isset($item['tab_connected'])) {
                $isConnected = (bool) $item['tab_connected'];
            }

            // A tab is truly online only if both IP and MAC exist AND physical tablet is connected
            $isHardwareBound = !empty($tab->active_mac) && !empty($tab->ip_address);
            $isOnline = $isHardwareBound && $isConnected;

            $tab->connection_status = $isOnline ? 'online' : 'offline';
            if ($isOnline) {
                $tab->last_seen_at = now();
                $onlineCount++;
            } else {
                $offlineCount++;
            }
            $tab->save();

            // Status record & telemetry persistence
            $status = $tab->statusRecord;
            if (!$status) {
                $status = TabStatus::create([
                    'tab_id'            => $tab->id,
                    'status'            => 'idle',
                    'lock_status'       => $tab->lock_status ?? 'unlocked',
                    'current_balance'   => 0.00,
                    'balance_version'   => 1,
                    'connection_status' => $tab->connection_status,
                    'active_mac'        => $tab->active_mac,
                    'last_synced_at'    => now(),
                ]);
            }

            $recoveryData = $status->recovery_data ?? [];
            $telemetry = $recoveryData['telemetry'] ?? [];
            $telemetry['tab_connected'] = $isConnected;

            if (isset($item['battery'])) {
                $telemetry['battery'] = (int) $item['battery'];
            }
            if (isset($item['is_charging'])) {
                $telemetry['is_charging'] = (bool) $item['is_charging'];
            }
            if (isset($item['wifi_rssi'])) {
                $telemetry['wifi_rssi'] = (int) $item['wifi_rssi'];
            }
            if (isset($item['app_version'])) {
                $telemetry['app_version'] = (string) $item['app_version'];
            }
            if (isset($item['last_ping_ms'])) {
                $telemetry['last_ping_ms'] = (int) $item['last_ping_ms'];
            }

            if (!$isConnected && !empty($item['disconnect_reason'])) {
                $telemetry['disconnect_reason'] = (string) $item['disconnect_reason'];
            } elseif ($isConnected) {
                unset($telemetry['disconnect_reason']);
            }

            $telemetry['last_telemetry_at'] = now()->toIso8601String();
            $recoveryData['telemetry'] = $telemetry;

            $status->update([
                'connection_status' => $tab->connection_status,
                'active_mac'        => $tab->active_mac,
                'last_synced_at'    => now(),
                'recovery_data'     => $recoveryData,
            ]);

            $processed[] = [
                'tab_id'            => $tab->id,
                'tab_number'        => $tab->tab_number,
                'table_name'        => $tab->table_name,
                'connection_status' => $tab->connection_status,
                'is_online'         => $isOnline,
                'lock_status'       => $tab->lock_status,
                'current_balance'   => (float) $status->current_balance,
                'telemetry'         => $telemetry,
            ];
        }

        return response()->json([
            'success'          => true,
            'message'          => "Batch telemetry synced successfully for " . count($processed) . " tab(s).",
            'station_id'       => $stationId,
            'total_processed'  => count($processed),
            'online_count'     => $onlineCount,
            'offline_count'    => $offlineCount,
            'tabs'             => $processed,
            'timestamp'        => now()->toIso8601String(),
        ]);
    }
}
