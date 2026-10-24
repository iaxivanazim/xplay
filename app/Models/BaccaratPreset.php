<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaccaratPreset extends Model
{
    protected $fillable = [
        'name',
        'min_bet',
        'max_bet',
        'burn_card',
        'side_min_bet',
        'side_max_bet',
        'commission',
        'baccarat_6_commission',
        // 'enable_pairbets',
        // 'enable_lucky6',
        'chip_preset_id',
        'status'
    ];

    protected $casts = [
        'commission'       => 'boolean',
        // 'enable_pairbets'  => 'boolean',
        // 'enable_lucky6'    => 'boolean',
        'baccarat_6_commission'=> 'boolean',
    ];

    // Dynamically returns the correct banker multiplier
    public function getBankerMultiplier(): float
    {
        return $this->commission ? 0.95 : 1.00;
    }

    public function getBaccarat6Multiplier(): float
{
    return $this->baccarat_6_commission ? 0.95 : 0.50;
}

    public function chipPreset()
    {
        return $this->belongsTo(Chip::class, 'chip_preset_id');
    }

    public function tableAssignment()
    {
        return $this->morphOne(GameTableConfig::class, 'preset');
    }
}
