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
}
