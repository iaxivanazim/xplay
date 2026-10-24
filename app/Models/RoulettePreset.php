<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoulettePreset extends Model
{
    protected $table = 'roulette_presets';

    protected $fillable = [
        'name',
        'roulette_type',
        'min_bet',
        'max_bet',
        'side_min_bet',
        'side_max_bet',
        'chip_preset_id',
        'status',
    ];

    public function chipPreset()
    {
        return $this->belongsTo(Chip::class, 'chip_preset_id');
    }
}
