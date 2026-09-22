<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaccaratHistory extends Model
{
    public $timestamps = false;
    protected $table = 'baccarat_history';

    protected $fillable = [
        'shoe_no',
        'table_id',
        'game_no',
        'tab_id',
        'player_cards',
        'banker_cards',
        'winner',
        'side_win',
        'bet_position',
        'bet_amount',
        'win_amount',
        'current_credit',
        'date_time',
    ];

    protected $casts = [
        'player_cards' => 'array',
        'banker_cards' => 'array',
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
