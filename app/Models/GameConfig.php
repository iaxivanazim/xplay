<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameConfig extends Model
{
    protected $table = 'game_configs';

    protected $fillable = [
        'name',
        'game_type_id',
        'min_bet',
        'max_bet',
        'side_min_bet',
        'side_max_bet',
        'burn_card',
        'roulette_type',
        'baccarat_6_commission',
        'commission',
        'tie_min',
        'tie_max',
        'pair_min',
        'pair_max',
        'super6_min',
        'super6_max',
        'andar_min',
        'andar_max',
        'bahar_min',
        'bahar_max',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'is_default'            => 'boolean',
        'baccarat_6_commission' => 'boolean',
        'side_min_bet'          => 'decimal:2',
        'side_max_bet'          => 'decimal:2',
        'commission'            => 'decimal:4',
        'tie_min'               => 'decimal:2',
        'tie_max'               => 'decimal:2',
        'pair_min'              => 'decimal:2',
        'pair_max'              => 'decimal:2',
        'super6_min'            => 'decimal:2',
        'super6_max'            => 'decimal:2',
        'andar_min'             => 'decimal:2',
        'andar_max'             => 'decimal:2',
        'bahar_min'             => 'decimal:2',
        'bahar_max'             => 'decimal:2',
    ];

    public function gameType()
    {
        return $this->belongsTo(GameType::class, 'game_type_id');
    }

    public function payoutRules()
    {
        return $this->hasMany(GameConfigPayoutRule::class, 'game_config_id');
    }

    public function activePayoutRules()
    {
        return $this->hasMany(GameConfigPayoutRule::class, 'game_config_id')
            ->where('is_active', 1)
            ->with('payoutRule');
    }

    public function tabs()
    {
        return $this->belongsToMany(Tab::class, 'tab_game_configs', 'game_config_id', 'tab_id')
            ->withPivot('is_active', 'assigned_by', 'assigned_at')
            ->withTimestamps();
    }
}
