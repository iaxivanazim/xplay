<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AndarbaharHistory extends Model
{
    public $timestamps = false;
    protected $table = 'andarbahar_history';

    protected $fillable = [
        'table_id', 'game_no', 'tab_id', 'joker_card', 'andar_cards', 'bahar_cards',
        'winner', 'side_win', 'bet_position', 'bet_amount',
        'win_amount', 'current_credit', 'date_time',
    ];

    protected $casts = [
        'andar_cards' => 'array',
        'bahar_cards' => 'array',
        'bet_position' => 'array',
        'side_win'     => 'array',
        'bet_amount'   => 'float',
        'win_amount'   => 'float',
        'current_credit' => 'float',
        'date_time'    => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }
}
