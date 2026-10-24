<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabStatus extends Model
{
    protected $table = 'tab_statuses';

    protected $fillable = [
        'tab_id',
        'status',
        'lock_status',
        'current_session_id',
        'current_otp',
        'player_name',
        'player_phone',
        'player_card_id',
        'session_started_at',
        'current_balance',
        'session_buyin',
        'session_cashout',
        'shift_total_in',
        'shift_total_out',
        'shift_total_bet',
        'shift_total_win',
        'shift_games_count',
        'shift_last_bet',
        'last_game_no',
        'last_game_at',
        'last_bet_amount',
        'last_win_amount',
        'recovery_data',
        'last_synced_at',
        'connection_status',
        'active_mac',
    ];

    protected $casts = [
        'session_started_at' => 'datetime',
        'last_game_at'       => 'datetime',
        'last_synced_at'     => 'datetime',
        'current_balance'    => 'decimal:2',
        'session_buyin'      => 'decimal:2',
        'session_cashout'    => 'decimal:2',
        'shift_total_in'     => 'decimal:2',
        'shift_total_out'    => 'decimal:2',
        'shift_total_bet'    => 'decimal:2',
        'shift_total_win'    => 'decimal:2',
        'shift_last_bet'     => 'decimal:2',
        'last_bet_amount'    => 'decimal:2',
        'last_win_amount'    => 'decimal:2',
        'recovery_data'      => 'array',
    ];

    public function tab()
    {
        return $this->belongsTo(Tab::class, 'tab_id');
    }

    public function session()
    {
        return $this->belongsTo(TabSession::class, 'current_session_id', 'session_id');
    }
}
