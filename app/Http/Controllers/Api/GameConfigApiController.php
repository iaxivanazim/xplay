<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameConfig;
use App\Models\GameType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameConfigApiController extends Controller
{
    /**
     * List all game configurations with their current device_ip.
     * GET /api/v1/game-configs
     */
    public function index(): JsonResponse
    {
        $configs = GameConfig::with('gameType:id,name,code')
            ->select('id', 'name', 'device_ip', 'game_type_id', 'is_default', 'is_active', 'updated_at')
            ->get()
            ->map(function ($cfg) {
                return [
                    'id'         => $cfg->id,
                    'name'       => $cfg->name,
                    'game_type'  => $cfg->gameType?->name,
                    'game_code'  => $cfg->gameType?->code,
                    'device_ip'  => $cfg->device_ip,
                    'is_default' => (bool)$cfg->is_default,
                    'is_active'  => (bool)$cfg->is_active,
                    'updated_at' => $cfg->updated_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'count'   => $configs->count(),
            'data'    => $configs,
        ]);
    }

    /**
     * Get the current device_ip for a specific game configuration.
     * GET /api/v1/game-configs/{id}/device-ip
     */
    public function getDeviceIp(Request $request, $id): JsonResponse
    {
        $config = $this->resolveConfig($request, $id);

        if (!$config) {
            return $this->configNotFoundResponse($id);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $config->id,
                'name'       => $config->name,
                'game_type'  => $config->gameType?->name,
                'game_code'  => $config->gameType?->code,
                'device_ip'  => $config->device_ip,
                'updated_at' => $config->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Insert / update device_ip for a game configuration from an external source.
     * POST|PUT /api/v1/game-configs/{id?}/device-ip
     */
    public function updateDeviceIp(Request $request, $id = null): JsonResponse
    {
        $config = $this->resolveConfig($request, $id);

        if (!$config) {
            return $this->configNotFoundResponse($id ?? $request->input('id'));
        }

        // Determine device IP:
        // 1. Explicitly passed in 'device_ip', 'ip', or 'ip_address'
        // 2. Clear flag to unset device_ip
        // 3. Fallback to client's incoming request IP ($request->ip())
        $rawIp = $request->input('device_ip', $request->input('ip', $request->input('ip_address')));

        if ($request->has('clear') && $request->boolean('clear')) {
            $deviceIp = null;
        } elseif ($rawIp !== null && trim((string)$rawIp) !== '') {
            $deviceIp = trim((string)$rawIp);
        } elseif (!$request->has('device_ip') && !$request->has('ip') && !$request->has('ip_address')) {
            // Auto-detect IP from incoming HTTP request
            $deviceIp = $request->ip();
        } else {
            $deviceIp = null;
        }

        // Validate IP format if an IP is given
        if ($deviceIp !== null) {
            $ipCandidate = preg_replace('/:\d+$/', '', $deviceIp);
            if (!filter_var($ipCandidate, FILTER_VALIDATE_IP) && !filter_var($deviceIp, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'success' => false,
                    'code'    => 'INVALID_IP_ADDRESS',
                    'message' => "The provided IP address '{$deviceIp}' is not a valid IPv4 or IPv6 address.",
                    'errors'  => [
                        'device_ip' => ['The device_ip must be a valid IPv4 or IPv6 address.'],
                    ],
                ], 422);
            }
        }

        $previousIp = $config->device_ip;
        $config->device_ip = $deviceIp;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => $deviceIp !== null
                ? "Device IP successfully set to '{$deviceIp}' for configuration '{$config->name}'."
                : "Device IP successfully cleared for configuration '{$config->name}'.",
            'data'    => [
                'id'                 => $config->id,
                'name'               => $config->name,
                'game_type'          => $config->gameType?->name,
                'game_code'          => $config->gameType?->code,
                'device_ip'          => $config->device_ip,
                'previous_device_ip' => $previousIp,
                'updated_at'         => $config->updated_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Resolve GameConfig by ID, name, or game type.
     */
    protected function resolveConfig(Request $request, $id = null): ?GameConfig
    {
        $id = $id ?? $request->input('id', $request->input('config_id', $request->input('game_config_id')));

        if ($id) {
            return GameConfig::with('gameType')->find($id);
        }

        if ($name = $request->input('name', $request->input('config_name'))) {
            $config = GameConfig::with('gameType')->where('name', $name)->first();
            if ($config) {
                return $config;
            }
        }

        $gameType = $request->input('game_type', $request->input('game_code'));
        if ($gameType) {
            $config = GameConfig::with('gameType')
                ->whereHas('gameType', function ($q) use ($gameType) {
                    $q->where('code', strtoupper($gameType))
                      ->orWhere('name', 'like', "%{$gameType}%");
                })
                ->orderBy('is_default', 'desc')
                ->first();

            if ($config) {
                return $config;
            }
        }

        if ($gameTypeId = $request->input('game_type_id')) {
            return GameConfig::with('gameType')
                ->where('game_type_id', $gameTypeId)
                ->orderBy('is_default', 'desc')
                ->first();
        }

        return null;
    }

    /**
     * Response for config not found.
     */
    protected function configNotFoundResponse($id): JsonResponse
    {
        $available = GameConfig::select('id', 'name', 'game_type_id', 'device_ip')->get();

        return response()->json([
            'success'           => false,
            'code'              => 'CONFIG_NOT_FOUND',
            'message'           => "Game Configuration not found for identifier '{$id}'.",
            'available_configs' => $available->map(fn($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'device_ip' => $c->device_ip,
            ]),
        ], 404);
    }
}
