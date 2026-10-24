<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabSession extends Model
{
    protected $table = 'tab_sessions';
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'tab_id',
        'otp',
        'player_name',
        'player_phone',
        'player_card_id',
        'buyin_amount',
        'cashout_amount',
        'opening_balance',
        'closing_balance',
        'total_bet',
        'total_win',
        'games_played',
        'opened_by',
        'closed_by',
        'status',
        'opened_at',
        'closed_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'buyin_amount'    => 'decimal:2',
        'cashout_amount'  => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'total_bet'       => 'decimal:2',
        'total_win'       => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public function tab()
    {
        return $this->belongsTo(Tab::class, 'tab_id');
    }

    public function openedByUser()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
