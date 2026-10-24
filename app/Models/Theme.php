<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'code',
        'primary_color',
        'secondary_color',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function tables()
    {
        return $this->hasMany(GameTable::class);
    }
}
