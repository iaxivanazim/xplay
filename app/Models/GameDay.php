<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameDay extends Model
{
    protected $fillable = [
        'gaming_date',
        'started_at',
        'ended_at',
        'duration_hours',
        'is_closed',
        'started_by',
        'closed_by'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_closed' => 'boolean'
    ];

    public static function current()
    {
        return self::where('is_closed', false)->first();
    }
}
