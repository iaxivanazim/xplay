<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\GameConfig;
use App\Services\TabBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TabApiController extends Controller
{
    protected TabBalanceService $balanceService;

    public function __construct(TabBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }
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
     * POST /api/v1/tabs/bulk
     * Bulk provisions tabs with one basic configuration.
     */
    public function bulkStore(Request $request)
    {
        return app(\App\Http\Controllers\TabController::class)->bulkStore($request);
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

        $incomingIp = $request->input('ip_address') ?: $tab->ip_address ?: $request->ip();
        $isHardwareBound = !empty($incomingMac) && !empty($incomingIp);

        $updateData = [
            'active_mac'        => $incomingMac,
            'ip_address'        => $incomingIp,
            'connection_status' => $isHardwareBound ? 'online' : 'offline',
            'last_seen_at'      => now(),
        ];

        $tab->update($updateData);

        if ($tab->statusRecord) {
            $tab->statusRecord->update([
                'active_mac'        => $incomingMac,
                'ip_address'        => $incomingIp,
                'connection_status' => $updateData['connection_status'],
                'last_synced_at'    => now(),
            ]);
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
                'connection_status' => $updateData['connection_status'],
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
                'device_ip'     => $cfg->device_ip,
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
                'balance_version'   => $status ? (int)($status->balance_version ?? 1) : 1,
                'last_operation'    => $this->balanceService->formatLastOperation($status),
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
                'balance_version'   => (int) ($status->balance_version ?? 1),
                'last_operation'    => $this->balanceService->formatLastOperation($status),
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
     * POST|GET /api/v1/tabs/{id}/heartbeat
     * Tab keepalive ping to sync status and receive authoritative balance
     * along with the last operation that changed it (BUYIN, CASHOUT, WIN, LOSS).
     */
    public function heartbeat(Request $request, $id)
    {
        $tab = Tab::with('statusRecord')->find($id)
            ?? Tab::with('statusRecord')->where('tab_number', $id)->first();

        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab with identifier '{$id}' not found.",
            ], 404);
        }

        // Auto-capture IP from payload or client IP if not set
        $incomingIp = $request->input('ip_address') ?: $request->ip();
        if (empty($tab->ip_address) && $incomingIp) {
            $tab->ip_address = $incomingIp;
        }

        // Auto-capture MAC from payload if provided
        if ($request->filled('mac') || $request->filled('active_mac')) {
            $incomingMac = strtoupper(str_replace('-', ':', trim($request->input('mac') ?: $request->input('active_mac'))));
            if (empty($tab->active_mac)) {
                $tab->active_mac = $incomingMac;
            }
        }

        // Check if physical tablet is reachable by middleware (default true if not specified)
        $tabConnected = true;
        if ($request->has('tab_connected')) {
            $tabConnected = $request->boolean('tab_connected');
        } elseif ($request->has('is_connected')) {
            $tabConnected = $request->boolean('is_connected');
        }

        // A tab is only online if both IP and MAC are registered AND physical tablet is connected
        $isHardwareBound = !empty($tab->active_mac) && !empty($tab->ip_address);
        $isOnline = $isHardwareBound && $tabConnected;
        $tab->connection_status = $isOnline ? 'online' : 'offline';
        if ($isOnline) {
            $tab->last_seen_at = now();
        }
        $tab->save();

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

        // Telemetry tracking (battery, charging, wifi_rssi, app_version, disconnect_reason)
        $recoveryData = $status->recovery_data ?? [];
        $telemetry = $recoveryData['telemetry'] ?? [];
        $telemetry['tab_connected'] = $tabConnected;

        if ($request->has('battery')) {
            $telemetry['battery'] = (int) $request->input('battery');
        }
        if ($request->has('is_charging')) {
            $telemetry['is_charging'] = (bool) $request->boolean('is_charging');
        }
        if ($request->has('wifi_rssi')) {
            $telemetry['wifi_rssi'] = (int) $request->input('wifi_rssi');
        }
        if ($request->has('app_version')) {
            $telemetry['app_version'] = (string) $request->input('app_version');
        }
        if ($request->has('last_ping_ms')) {
            $telemetry['last_ping_ms'] = (int) $request->input('last_ping_ms');
        }

        if (!$tabConnected && $request->filled('disconnect_reason')) {
            $telemetry['disconnect_reason'] = (string) $request->input('disconnect_reason');
        } elseif ($tabConnected) {
            unset($telemetry['disconnect_reason']);
        }

        $telemetry['last_telemetry_at'] = now()->toIso8601String();
        $recoveryData['telemetry'] = $telemetry;

        $updateData = [
            'connection_status' => $tab->connection_status,
            'active_mac'        => $tab->active_mac,
            'last_synced_at'    => now(),
            'recovery_data'     => $recoveryData,
        ];

        if ($request->filled('last_game_no')) {
            $updateData['last_game_no'] = $request->last_game_no;
            $updateData['last_game_at'] = now();
        }

        $status->update($updateData);

        $currentBalance = (float) $status->current_balance;
        $balanceVersion = (int) ($status->balance_version ?? 1);

        // Detect if balance changed since client's known state
        $balanceChanged = false;
        if ($request->has('known_version')) {
            $balanceChanged = ((int) $request->input('known_version') !== $balanceVersion);
        } elseif ($request->has('known_balance')) {
            $balanceChanged = (abs((float) $request->input('known_balance') - $currentBalance) > 0.001);
        } elseif ($request->has('current_balance')) {
            $balanceChanged = (abs((float) $request->input('current_balance') - $currentBalance) > 0.001);
        } elseif ($status->last_op_at !== null) {
            $balanceChanged = true;
        }

        $lastOperation = $this->balanceService->formatLastOperation($status);
        $currentOtp = $status->current_otp;
        $hasActiveSession = ($currentBalance > 0 || $tab->currentSession !== null);
        $requiresOtpPrompt = ($hasActiveSession && empty($currentOtp));

        return response()->json([
            'success'             => true,
            'tab_id'              => $tab->id,
            'tab_number'          => $tab->tab_number,
            'tab_name'            => $tab->table_name,
            'lock_status'         => $tab->lock_status,
            'connection_status'   => $tab->connection_status,
            'is_online'           => $tab->isOnline(),
            'current_balance'     => $currentBalance,
            'balance_version'     => $balanceVersion,
            'balance_changed'     => $balanceChanged,
            'last_operation'      => $lastOperation,
            'current_otp'         => $currentOtp,
            'otp_set'             => !empty($currentOtp),
            'requires_otp_prompt' => $requiresOtpPrompt,
            'telemetry'           => $telemetry,
            'timestamp'           => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/win
     * External API endpoint to record a game win payout on a tab.
     */
    public function recordWin(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount'    => 'required|numeric|min:0.01',
            'game_no'   => 'nullable|string|max:100',
            'game_type' => 'nullable|string|max:50',
            'notes'     => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tab = Tab::find($id) ?? Tab::where('tab_number', $id)->first();
        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $result = $this->balanceService->applyOperation(
            tab: $tab,
            type: TabBalanceService::OP_WIN,
            amount: (float) $request->amount,
            reference: $request->game_no,
            notes: $request->notes ?? ('Game Win' . ($request->game_type ? " ({$request->game_type})" : '')),
            metadata: array_filter([
                'game_no'   => $request->game_no,
                'game_type' => $request->game_type,
            ])
        );

        return response()->json([
            'success' => true,
            'message' => "Win payout of \${$request->amount} recorded for Tab '{$tab->table_name}'.",
            'data'    => $result,
        ], 200);
    }

    /**
     * POST /api/v1/tabs/{id}/loss
     * External API endpoint to record a loss / lost bet on a tab.
     */
    public function recordLoss(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount'    => 'required|numeric|min:0.01',
            'game_no'   => 'nullable|string|max:100',
            'game_type' => 'nullable|string|max:50',
            'notes'     => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tab = Tab::find($id) ?? Tab::where('tab_number', $id)->first();
        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $result = $this->balanceService->applyOperation(
            tab: $tab,
            type: TabBalanceService::OP_LOSS,
            amount: (float) $request->amount,
            reference: $request->game_no,
            notes: $request->notes ?? ('Game Loss' . ($request->game_type ? " ({$request->game_type})" : '')),
            metadata: array_filter([
                'game_no'   => $request->game_no,
                'game_type' => $request->game_type,
            ])
        );

        return response()->json([
            'success' => true,
            'message' => "Loss of \${$request->amount} recorded for Tab '{$tab->table_name}'.",
            'data'    => $result,
        ], 200);
    }

    /**
     * POST /api/v1/tabs/{id?}/game-result
     * External API endpoint to record a full round result (bet and win).
     */
    public function recordGameResult(Request $request, $id = null)
    {
        $id = $id ?? $request->input('tab_id', $request->input('id'));

        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:0',
            'win_amount' => 'required|numeric|min:0',
            'game_no'    => 'nullable|string|max:100',
            'game_type'  => 'nullable|string|max:50',
            'notes'      => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tab = Tab::find($id) ?? Tab::where('tab_number', $id)->first();
        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $result = $this->balanceService->applyGameResult(
            tab: $tab,
            betAmount: (float) $request->bet_amount,
            winAmount: (float) $request->win_amount,
            gameNo: $request->game_no,
            gameType: $request->game_type ?? 'ETG',
            metadata: array_filter([
                'notes' => $request->notes,
            ])
        );

        return response()->json([
            'success' => true,
            'message' => "Game result recorded for Tab '{$tab->table_name}'.",
            'data'    => $result,
        ], 200);
    }

    /**
     * GET /api/v1/tabs/{id}/balance-logs
     * Returns the recent balance changes / audit trail for the tab.
     */
    public function balanceLogs(Request $request, $id)
    {
        $tab = Tab::find($id) ?? Tab::where('tab_number', $id)->first();
        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $limit = min(100, max(1, (int) $request->input('limit', 20)));
        $sinceVersion = $request->has('since_version') ? (int) $request->input('since_version') : null;

        $logs = $this->balanceService->getRecentLogs($tab, $limit, $sinceVersion);

        return response()->json([
            'success'         => true,
            'tab_id'          => $tab->id,
            'tab_number'      => $tab->tab_number,
            'current_balance' => (float) ($tab->statusRecord?->current_balance ?? 0.00),
            'balance_version' => (int) ($tab->statusRecord?->balance_version ?? 1),
            'count'           => count($logs),
            'data'            => $logs,
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/lock
     * Lock the tab into Break Mode.
     * If this is the first lock of the session (current_otp is null), the player must provide their desired OTP.
     * Until the player provides/generates the OTP, the OTP column remains null.
     */
    public function lock(Request $request, $id)
    {
        $tab = Tab::with(['statusRecord', 'currentSession'])->find($id)
            ?? Tab::with(['statusRecord', 'currentSession'])->where('tab_number', $id)->first();

        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $status = $tab->statusRecord;
        $session = $tab->currentSession;
        $hasActiveSession = ($session !== null || ($status && $status->current_balance > 0));

        // Check if OTP has not been generated yet for this session
        $isFirstLock = ($hasActiveSession && empty($status?->current_otp));

        if ($isFirstLock && !$request->filled('otp') && !$request->boolean('force')) {
            return response()->json([
                'success'      => false,
                'code'         => 'OTP_REQUIRED',
                'requires_otp' => true,
                'message'      => 'A session OTP must be generated by the player upon first time locking the tab.',
                'lock_status'  => $tab->lock_status,
            ], 422);
        }

        // If player provided OTP, validate and store it
        if ($request->filled('otp')) {
            $validator = Validator::make($request->all(), [
                'otp' => 'required|string|min:4|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $otp = trim($request->otp);

            if ($session) {
                $session->update(['otp' => $otp]);
            }

            if ($status) {
                $recData = is_array($status->recovery_data) ? $status->recovery_data : [];
                $recData['otp'] = $otp;
                $status->update([
                    'current_otp'   => $otp,
                    'recovery_data' => $recData,
                ]);
            }
        }

        $tab->update([
            'lock_status' => 'locked',
            'locked_at'   => now(),
            'locked_by'   => auth()->id(),
        ]);

        if ($status) {
            $status->update([
                'lock_status' => 'locked',
                'status'      => 'locked',
            ]);
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Tab locked successfully.',
            'tab_id'        => $tab->id,
            'tab_number'    => $tab->tab_number,
            'lock_status'   => 'locked',
            'otp_set'       => !empty($status?->fresh()->current_otp),
            'current_otp'   => $status?->fresh()->current_otp,
            'is_first_lock' => $isFirstLock,
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/unlock
     * Unlock the tab using the player-generated session OTP.
     */
    public function unlock(Request $request, $id)
    {
        $tab = Tab::with('statusRecord')->find($id)
            ?? Tab::with('statusRecord')->where('tab_number', $id)->first();

        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $status = $tab->statusRecord;

        // If OTP is set for this session, verify it (unless cashier override is passed)
        if (!empty($status?->current_otp) && !$request->boolean('override') && !$request->boolean('force')) {
            $enteredOtp = trim((string)$request->input('otp'));
            if ($enteredOtp !== (string)$status->current_otp) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INVALID_OTP',
                    'message' => 'Incorrect OTP. Please enter the valid session OTP.',
                ], 403);
            }
        }

        $tab->update([
            'lock_status' => 'unlocked',
            'locked_at'   => null,
            'locked_by'   => null,
        ]);

        if ($status) {
            $status->update([
                'lock_status' => 'unlocked',
                'status'      => ((float)$status->current_balance > 0) ? 'active' : 'idle',
            ]);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Tab unlocked successfully.',
            'tab_id'      => $tab->id,
            'tab_number'  => $tab->tab_number,
            'lock_status' => 'unlocked',
        ]);
    }

    /**
     * POST /api/v1/tabs/{id}/set-otp
     * Set the player-generated session OTP.
     */
    public function setOtp(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|min:4|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tab = Tab::with(['statusRecord', 'currentSession'])->find($id)
            ?? Tab::with(['statusRecord', 'currentSession'])->where('tab_number', $id)->first();

        if (!$tab) {
            return response()->json([
                'success' => false,
                'message' => "Tab '{$id}' not found.",
            ], 404);
        }

        $otp = trim($request->otp);

        if ($tab->currentSession) {
            $tab->currentSession->update(['otp' => $otp]);
        }

        if ($tab->statusRecord) {
            $recData = is_array($tab->statusRecord->recovery_data) ? $tab->statusRecord->recovery_data : [];
            $recData['otp'] = $otp;
            $tab->statusRecord->update([
                'current_otp'   => $otp,
                'recovery_data' => $recData,
            ]);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Session OTP successfully set by player.',
            'tab_id'      => $tab->id,
            'current_otp' => $otp,
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
