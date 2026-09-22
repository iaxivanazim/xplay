<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableFloat extends Model
{
    protected $primaryKey = 'float_id';

    protected $fillable = [
        'table_id', 'float_open', 'float_close',
        'opened_by', 'closed_by', 'status', 'gameday',
        'opened_at', 'closed_at'
    ];

    protected $casts = [
        'gameday'   => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'float_open'  => 'decimal:2',
        'float_close' => 'decimal:2',
    ];

    public function gameTable()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeOpen($query)
    {
        return $query->where('status', 1);
    }

    public function scopeToday($query)
    {
        return $query->where('gameday', today());
    }
}
