<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabGameConfig extends Model
{
    protected $table = 'tab_game_configs';

    protected $fillable = [
        'tab_id',
        'game_config_id',
        'is_active',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function tab()
    {
        return $this->belongsTo(Tab::class, 'tab_id');
    }

    public function gameConfig()
    {
        return $this->belongsTo(GameConfig::class, 'game_config_id');
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
