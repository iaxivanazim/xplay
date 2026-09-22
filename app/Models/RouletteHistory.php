<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RouletteHistory — stores one bet per row for each Roulette spin.
 *
 * Multiple rows share the same game_no for a single spin (one per bet position placed).
 *
 * The game station sends the winning_number.
 * xplay determines winning_colour and calculates win_amount from payout rules.
 *
 * Winning colour logic (European):
 *   Green  → 0
 *   Red    → 1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36
 *   Black  → all other 1-36
 *
 * @property int    $id
 * @property int    $table_id
 * @property string|null $game_no
 * @property string|null $tab_id
 * @property int    $winning_number
 * @property string $winning_colour   'red' | 'black' | 'green'
 * @property string $roulette_type    'european' | 'american'
 * @property string $bet_position     Matches payout_rules.bet_position (e.g. 'RED', 'STRAIGHT')
 * @property array|null $bet_numbers  Numbers covered by the bet
 * @property float  $bet_amount
 * @property float  $win_amount
 * @property float  $current_credit
 * @property \DateTime $date_time
 */
class RouletteHistory extends Model
{
    public $timestamps = false;

    protected $table = 'roulette_history';

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
        'date_time',
    ];

    protected $casts = [
        'bet_numbers'    => 'array',
        'bet_amount'     => 'float',
        'win_amount'     => 'float',
        'current_credit' => 'float',
        'date_time'      => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function table()
    {
        return $this->belongsTo(GameTable::class, 'table_id');
    }

    // ── Static Helpers ───────────────────────────────────────────

    /**
     * European Roulette red numbers.
     */
    private const RED_NUMBERS = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];

    /**
     * Determine the colour of a winning number for European roulette.
     * Returns 'green', 'red', or 'black'.
     */
    public static function resolveColour(int $number, string $type = 'european'): string
    {
        if ($number === 0) return 'green';
        if ($type === 'american' && $number === 37) return 'green'; // 00 stored as 37

        return in_array($number, self::RED_NUMBERS) ? 'red' : 'black';
    }

    /**
     * Determine whether a given bet_position wins for a given winning_number.
     * Used by xplay result calculation engine to set win_amount.
     *
     * @param string $betPosition  Payout rule code (e.g. 'RED', 'STRAIGHT', 'ODD')
     * @param array  $betNumbers   Numbers covered by the bet (for inside bets)
     * @param int    $winningNumber The spin result
     * @return bool
     */
    public static function isWinningBet(string $betPosition, array $betNumbers, int $winningNumber): bool
    {
        return match ($betPosition) {
            // Inside bets — covered numbers array determines win
            'STRAIGHT', 'SPLIT', 'STREET', 'CORNER', 'LINE' =>
                in_array($winningNumber, $betNumbers),

            // Outside bets — evaluated from winning number
            'RED'    => in_array($winningNumber, self::RED_NUMBERS),
            'BLACK'  => $winningNumber > 0 && !in_array($winningNumber, self::RED_NUMBERS),
            'EVEN'   => $winningNumber > 0 && $winningNumber % 2 === 0,
            'ODD'    => $winningNumber > 0 && $winningNumber % 2 !== 0,
            'LOW'    => $winningNumber >= 1 && $winningNumber <= 18,
            'HIGH'   => $winningNumber >= 19 && $winningNumber <= 36,
            'COLUMN' => in_array($winningNumber, $betNumbers),     // column numbers passed in
            'DOZEN'  => in_array($winningNumber, $betNumbers),     // dozen numbers passed in

            default  => false,
        };
    }
}
