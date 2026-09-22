<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chip extends Model
{
    protected $fillable = [
        'preset_name',
        'chip_1_value',
        'chip_2_value',
        'chip_3_value',
        'chip_4_value',
        'chip_5_value',
        'base_value',
        'status'
    ];
}
