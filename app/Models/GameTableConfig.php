<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameTableConfig extends Model
{
    protected $fillable = ['table_id', 'preset_type', 'preset_id', 'assigned_by', 'assigned_at'];

    public function preset()
    {
        return $this->morphTo();
    }

    public function table()
    {
        return $this->belongsTo(GameTable::class);
    }
}
