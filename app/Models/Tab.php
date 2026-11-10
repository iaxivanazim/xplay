<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tab extends Model
{
    protected $table = 'tabs';

    protected $fillable = [
        'tab_number',
        'table_name', // Used as Tab Name (e.g. Amatic-1, Apex-13)
        'game_type_id',
        'game_config_id',
        'active_mac',
        'ip_address',
        'float',
        'bet_index',
        'status', // 1=enabled, 0=disabled
        'lock_status', // 'unlocked', 'locked', 'break'
        'locked_at',
        'locked_by',
        'denomination',
        'last_seen_at',
        'connection_status', // 'online', 'offline', 'disconnected'
        'felt_color',
    ];

    protected $casts = [
        'status'       => 'boolean',
        'denomination' => 'decimal:4',
        'locked_at'    => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function gameType()
    {
        return $this->belongsTo(GameType::class, 'game_type_id');
    }

    public function gameConfig()
    {
        return $this->belongsTo(GameConfig::class, 'game_config_id');
    }

    public function gameConfigs()
    {
        return $this->belongsToMany(GameConfig::class, 'tab_game_configs', 'tab_id', 'game_config_id')
            ->withPivot('is_active', 'assigned_by', 'assigned_at')
            ->withTimestamps();
    }

    public function tabGameConfigs()
    {
        return $this->hasMany(TabGameConfig::class, 'tab_id');
    }

    public function statusRecord()
    {
        return $this->hasOne(TabStatus::class, 'tab_id');
    }

    public function currentSession()
    {
        return $this->hasOne(TabSession::class, 'tab_id')
            ->where('status', 'active')
            ->latest('session_id');
    }

    public function sessions()
    {
        return $this->hasMany(TabSession::class, 'tab_id');
    }

    public function balanceLogs()
    {
        return $this->hasMany(TabBalanceLog::class, 'tab_id')->latest();
    }

    public function ledgers()
    {
        return $this->hasMany(TableLedger::class, 'tab_id', 'id');
    }

    // Helper to get Tab Name
    public function getNameAttribute(): string
    {
        return $this->table_name ?: ('Tab-' . ($this->tab_number ?: $this->id));
    }

    // Helper to get Tab Number
    public function getNumberAttribute(): int
    {
        return (int) ($this->tab_number ?: $this->id);
    }

    /**
     * Heartbeat timeout threshold in seconds.
     * Devices not pinging within this interval are considered timed out and offline.
     */
    public const HEARTBEAT_TIMEOUT_SECONDS = 90;

    /**
     * Determine if the tab hardware is physically active and online ('A' status).
     * 
     * A tab is only online when:
     * 1. Both IP address and MAC address are registered (not null/empty).
     * 2. Its connection_status is 'online'.
     * 3. A constant heartbeat has been reported recently (last_seen_at within 90 seconds).
     */
    public function isOnline(): bool
    {
        if (empty(trim((string)$this->ip_address)) || empty(trim((string)$this->active_mac))) {
            return false;
        }

        if ($this->connection_status !== 'online') {
            return false;
        }

        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gte(now()->subSeconds(self::HEARTBEAT_TIMEOUT_SECONDS));
    }

    /**
     * Accessor for $tab->is_online.
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    /**
     * Synchronize stale connections in the database.
     * Any tab marked 'online' in database that lacks hardware bindings (IP/MAC)
     * or whose heartbeat has timed out is updated to 'offline'.
     */
    public static function syncStaleConnections(): int
    {
        $timeoutThreshold = now()->subSeconds(self::HEARTBEAT_TIMEOUT_SECONDS);

        $affected = static::where('connection_status', 'online')
            ->where(function ($query) use ($timeoutThreshold) {
                $query->whereNull('active_mac')
                    ->orWhere('active_mac', '')
                    ->orWhereNull('ip_address')
                    ->orWhere('ip_address', '')
                    ->orWhereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $timeoutThreshold);
            })
            ->update(['connection_status' => 'offline']);

        if ($affected > 0) {
            TabStatus::where('connection_status', 'online')
                ->whereIn('tab_id', function ($query) {
                    $query->select('id')->from('tabs')->where('connection_status', 'offline');
                })
                ->update(['connection_status' => 'offline']);
        }

        return $affected;
    }
}
