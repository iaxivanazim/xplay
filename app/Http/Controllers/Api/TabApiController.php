<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\GameConfig;
use Illuminate\Http\Request;

class TabApiController extends Controller
{
    /**
     * GET /api/v1/tabs
     */
    public function index()
    {
        $tabs = Tab::with(['gameConfig.gameType', 'gameConfigs.gameType', 'statusRecord'])
            ->where('status', 1)
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $tabs->count(),
            'data'    => $tabs->map(fn($t) => $this->formatTab($t)),
        ]);
    }

    /**
     * GET /api/v1/tabs/{id}
     */
    public function show($id)
    {
        $tab = Tab::with(['gameConfig.gameType', 'gameConfigs.gameType', 'statusRecord'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatTab($tab),
        ]);
    }

    /**
     * GET /api/v1/tabs/by-mac/{mac}
     * Used when Tab boots up to identify itself.
     */
    public function byMac($mac)
    {
        $cleanMac = strtoupper(str_replace('-', ':', $mac));
        $tab = Tab::with(['gameConfig.gameType', 'gameConfigs.gameType', 'statusRecord'])
            ->where('active_mac', $cleanMac)
            ->first();

        if (!$tab) {
            return response()->json([
                'success' => false,
                'code'    => 'TAB_NOT_BOUND',
                'message' => "No Tab registered with MAC '{$cleanMac}'.",
            ], 404);
        }

        $tab->update([
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatTab($tab),
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/register-mac
     * Provisions / binds a physical tablet's MAC address to a Tab.
     */
    public function registerMac(Request $request, $id)
    {
        // Support either 'mac' or 'mac_address'
        if (!$request->has('mac_address') && $request->has('mac')) {
            $request->merge(['mac_address' => $request->input('mac')]);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
            'ip_address'  => ['nullable', 'string'],
            'override'    => ['nullable', 'boolean'],
            'force'       => ['nullable', 'boolean'],
        ], [
            'mac_address.required' => 'The MAC address is required (pass "mac" or "mac_address").',
            'mac_address.regex'    => 'The MAC address format is invalid (expected XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Allow tab lookup by ID or tab_number
        $tab = Tab::find($id) ?? Tab::where('tab_number', $id)->first();
        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab with identifier '{$id}' not found.",
            ], 404);
        }

        $incomingMac = strtoupper(str_replace('-', ':', $request->mac_address));
        $override = $request->boolean('override') || $request->boolean('force');

        // Check if this MAC is already bound to another Tab
        $conflictTab = Tab::where('active_mac', $incomingMac)
            ->where('id', '!=', $tab->id)
            ->first();

        if ($conflictTab) {
            if (!$override) {
                return response()->json([
                    'success' => false,
                    'code'    => 'MAC_ALREADY_ASSIGNED',
                    'message' => "MAC '{$incomingMac}' is already bound to Tab '{$conflictTab->table_name}' (ID: {$conflictTab->id}). Pass 'override': true to reassign.",
                    'conflicting_tab' => [
                        'id'         => $conflictTab->id,
                        'tab_number' => $conflictTab->tab_number,
                        'table_name' => $conflictTab->table_name,
                    ],
                ], 409);
            }

            // If override is true, unbind the MAC from the previous tab
            $conflictTab->update([
                'active_mac' => null,
                'connection_status' => 'offline',
            ]);
            if ($conflictTab->statusRecord) {
                $conflictTab->statusRecord->update(['active_mac' => null]);
            }
        }

        // Check if the target tab is already bound to a different MAC
        if ($tab->active_mac && $tab->active_mac !== $incomingMac && !$override) {
            return response()->json([
                'success' => false,
                'code'    => 'TAB_ALREADY_BOUND',
                'message' => "Tab '{$tab->table_name}' is already bound to MAC {$tab->active_mac}. Pass 'override': true to replace.",
                'current_mac' => $tab->active_mac,
            ], 409);
        }

        $updateData = [
            'active_mac'        => $incomingMac,
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ];

        if ($request->filled('ip_address')) {
            $updateData['ip_address'] = $request->ip_address;
        }

        $tab->update($updateData);

        if ($tab->statusRecord) {
            $statusUpdate = ['active_mac' => $incomingMac];
            if ($request->filled('ip_address')) {
                $statusUpdate['ip_address'] = $request->ip_address;
            }
            $tab->statusRecord->update($statusUpdate);
        }

        return response()->json([
            'success' => true,
            'message' => "MAC '{$incomingMac}' registered successfully for Tab '{$tab->table_name}'.",
            'data'    => [
                'tab_id'            => $tab->id,
                'tab_number'        => $tab->tab_number,
                'table_name'        => $tab->table_name,
                'active_mac'        => $incomingMac,
                'ip_address'        => $tab->ip_address,
                'connection_status' => 'online',
            ],
        ]);
    }

    /**
     * GET /api/v1/tabs/{id}/configuration
     * Returns full multi-game configurations and rules assigned to this Tab.
     */
    public function configuration($id)
    {
        $tab = Tab::with([
            'gameConfig.gameType',
            'gameConfig.payoutRules.payoutRule',
            'gameConfigs.gameType',
            'gameConfigs.payoutRules.payoutRule',
            'statusRecord',
        ])->findOrFail($id);

        $configs = $tab->gameConfigs->map(function ($cfg) {
            return [
                'id'            => $cfg->id,
                'name'          => $cfg->name,
                'game_type'     => $cfg->gameType?->name,
                'game_code'     => $cfg->gameType?->code,
                'min_bet'       => $cfg->min_bet,
                'max_bet'       => $cfg->max_bet,
                'side_min_bet'  => $cfg->side_min_bet,
                'side_max_bet'  => $cfg->side_max_bet,
                'roulette_type' => $cfg->roulette_type,
                'commission'    => $cfg->commission,
                'payout_rules'  => $cfg->payoutRules->where('is_active', 1)->map(fn($r) => [
                    'rule_id'    => $r->payout_id,
                    'bet_name'   => $r->payoutRule?->bet_name,
                    'position'   => $r->payoutRule?->bet_position,
                    'multiplier' => $r->payout_multiplier ?? $r->payoutRule?->payout_multiplier,
                ])->values(),
            ];
        });

        return response()->json([
            'success'           => true,
            'tab_id'            => $tab->id,
            'tab_number'        => $tab->tab_number,
            'tab_name'          => $tab->table_name,
            'denomination'      => (float) $tab->denomination,
            'lock_status'       => $tab->lock_status,
            'primary_config_id' => $tab->game_config_id,
            'assigned_games'    => $configs,
        ]);
    }

    /**
     * GET /api/v1/tabs/{id}/status
     * Returns live status snapshot for quick polling or state check.
     */
    public function status($id)
    {
        $tab = Tab::with('statusRecord')->findOrFail($id);
        $status = $tab->statusRecord;

        return response()->json([
            'success' => true,
            'data'    => [
                'tab_id'            => $tab->id,
                'tab_number'        => $tab->tab_number,
                'tab_name'          => $tab->table_name,
                'status'            => $status?->status ?? 'idle',
                'lock_status'       => $tab->lock_status,
                'current_balance'   => $status ? (float)$status->current_balance : 0.00,
                'current_otp'       => $status?->current_otp,
                'player_name'       => $status?->player_name,
                'last_game_no'      => $status?->last_game_no,
                'last_synced_at'    => $status?->last_synced_at?->toIso8601String(),
                'connection_status' => $status?->connection_status ?? 'online',
            ],
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/restore
     * CRITICAL FAILURE RESTORATION ENDPOINT:
     * When a Tab crashes, loses power, drops network, or reboots,
     * it calls this endpoint to restore its exact state, session, OTP, and balance.
     */
    public function restore(Request $request, $id)
    {
        $tab = Tab::with(['statusRecord', 'currentSession', 'gameConfig.gameType'])->findOrFail($id);
        $status = $tab->statusRecord;

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'No prior state snapshot found for this Tab.',
            ], 404);
        }

        $tab->update([
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);

        $status->update([
            'connection_status' => 'online',
            'last_synced_at'    => now(),
        ]);

        return response()->json([
            'success'        => true,
            'message'        => 'Session and state restored successfully.',
            'restored_state' => [
                'tab_id'            => $tab->id,
                'tab_number'        => $tab->tab_number,
                'tab_name'          => $tab->table_name,
                'denomination'      => (float) $tab->denomination,
                'status'            => $status->status,
                'lock_status'       => $status->lock_status,
                'current_balance'   => (float) $status->current_balance,
                'current_otp'       => $status->current_otp,
                'player_name'       => $status->player_name,
                'player_phone'      => $status->player_phone,
                'player_card_id'    => $status->player_card_id,
                'session_started'   => $status->session_started_at?->toIso8601String(),
                'session_id'        => $status->current_session_id,
                'active_game'       => [
                    'config_id' => $tab->game_config_id,
                    'name'      => $tab->gameConfig?->name,
                    'game_type' => $tab->gameConfig?->gameType?->name,
                    'game_code' => $tab->gameConfig?->gameType?->code,
                ],
                'recovery_payload'  => $status->recovery_data,
                'restored_at'       => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/heartbeat
     * Tab keepalive ping to sync status and last bet.
     */
    public function heartbeat(Request $request, $id)
    {
        $tab = Tab::findOrFail($id);

        $tab->update([
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);

        if ($tab->statusRecord) {
            $updateData = [
                'connection_status' => 'online',
                'last_synced_at'    => now(),
            ];

            if ($request->filled('last_game_no')) {
                $updateData['last_game_no'] = $request->last_game_no;
                $updateData['last_game_at'] = now();
            }

            if ($request->filled('current_balance')) {
                $updateData['current_balance'] = (float) $request->current_balance;
            }

            $tab->statusRecord->update($updateData);
        }

        return response()->json([
            'success'     => true,
            'lock_status' => $tab->lock_status,
            'timestamp'   => now()->toIso8601String(),
        ]);
    }

    private function formatTab(Tab $tab): array
    {
        $status = $tab->statusRecord;

        return [
            'id'                => $tab->id,
            'tab_number'        => $tab->tab_number,
            'tab_name'          => $tab->table_name,
            'active_mac'        => $tab->active_mac,
            'ip_address'        => $tab->ip_address,
            'denomination'      => (float) $tab->denomination,
            'status'            => (bool) $tab->status,
            'lock_status'       => $tab->lock_status,
            'connection_status' => $tab->connection_status,
            'game_config'       => $tab->gameConfig ? [
                'id'        => $tab->gameConfig->id,
                'name'      => $tab->gameConfig->name,
                'game_type' => $tab->gameConfig->gameType?->name,
                'game_code' => $tab->gameConfig->gameType?->code,
            ] : null,
            'current_balance'   => $status ? (float)$status->current_balance : 0.00,
            'current_otp'       => $status?->current_otp,
            'player_name'       => $status?->player_name,
            'last_seen_at'      => $tab->last_seen_at?->toIso8601String(),
        ];
    }
}
