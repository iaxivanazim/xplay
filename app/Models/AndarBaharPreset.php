<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AndarBaharPreset extends Model
{
    protected $table = 'andarbahar_presets';
    protected $fillable = [
        'name',
        'min_bet',
        'max_bet',
        'burn_card',
        'enable_super_andar',
        'enable_super_bahar',
        'chip_preset_id',
        'status'
    ];

    public function chipPreset()
    {
        return $this->belongsTo(Chip::class, 'chip_preset_id');
    }
    public function tableAssignment()
    {
        return $this->morphOne(GameTableConfig::class, 'preset');
    }
}
