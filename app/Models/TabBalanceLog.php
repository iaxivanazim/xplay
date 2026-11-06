<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabBalanceLog extends Model
{
    protected $table = 'tab_balance_logs';

    protected $fillable = [
        'tab_id',
        'session_id',
        'balance_version',
        'op_type',
        'amount',
        'prev_balance',
        'new_balance',
        'reference',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'balance_version' => 'integer',
        'amount'          => 'decimal:2',
        'prev_balance'    => 'decimal:2',
        'new_balance'     => 'decimal:2',
        'metadata'        => 'array',
    ];

    public function tab()
    {
        return $this->belongsTo(Tab::class, 'tab_id');
    }

    public function session()
    {
        return $this->belongsTo(TabSession::class, 'session_id');
    }
}
