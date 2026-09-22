<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    public function tables()
    {
        return $this->hasMany(GameTable::class);
    }

    public function payoutRules()
    {
        return $this->hasMany(PayoutRule::class, 'game_type_id');
    }
}
