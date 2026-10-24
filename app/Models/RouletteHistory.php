<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouletteHistory extends Model
{
    protected $table = 'roulette_history';
    public $timestamps = true;

    protected $fillable = [
        'table_id',
        'game_no',
        'tab_id',
        'winning_number',
        'winning_colour',
        'roulette_type',
        'bet_position',
        'bet_numbers',
        'bet_amount',
        'win_amount',
        'current_credit',
        'round_bets',
        'date_time',
    ];

    protected $casts = [
        'winning_number' => 'integer',
        'bet_position'   => 'array',
        'bet_numbers'    => 'array',
        'round_bets'     => 'array',
        'bet_amount'     => 'decimal:2',
        'win_amount'     => 'decimal:2',
        'current_credit' => 'decimal:2',
        'date_time'      => 'datetime',
    ];

    public function gameTable()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }

    public function tab()
    {
        return $this->belongsTo(Tab::class, 'tab_id', 'tab_number');
    }
}
