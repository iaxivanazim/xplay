<?php

namespace App\Services;

use App\Models\PayoutRule;
use App\Models\RouletteHistory;
use App\Models\Tab;
use App\Models\TabStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RouletteEngine
{
    /**
     * Standard European Roulette numbers colored red.
     */
    public const RED_NUMBERS = [
        1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36
    ];

    /**
     * Standard European Roulette numbers colored black.
     */
    public const BLACK_NUMBERS = [
        2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35
    ];

    /**
     * Call Bet Number Sets (European/French Call Bets)
     */
    public const CALL_BETS = [
        '0-SPIEL'        => [12, 35, 3, 26, 0, 32, 15],
        'SERIES 0/2/3'   => [22, 18, 29, 7, 28, 12, 35, 3, 26, 0, 32, 15, 19, 4, 21, 2, 25],
        'ORPHELINS'      => [1, 20, 14, 31, 9, 17, 34, 6],
        'SERIES 5/8'     => [27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33],
        'CROWN'          => [0, 1, 2, 3, 12, 15, 26, 32, 35],
        'NEIGHBOURS'     => [], // dynamic based on center number
    ];

    /**
     * Columns definition (Row 1 = Col 3, Row 2 = Col 2, Row 3 = Col 1)
     */
    public const COLUMNS = [
        'COL_3' => [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36], // Top row in table
        'COL_2' => [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35], // Middle row
        'COL_1' => [1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34], // Bottom row
    ];

    /**
     * Dozens definition
     */
    public const DOZENS = [
        'DOZEN_1' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
        'DOZEN_2' => [13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24],
        'DOZEN_3' => [25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36],
    ];

    /**
     * Cached static definitions for all 157 betting positions (indices 0 to 156)
     * strictly matching WhatsApp Image 2026-10-21 at 6.49.23 PM.jpeg
     */
    private static ?array $allPositions = null;

    /**
     * Generate and retrieve all 157 betting positions matching hardware layout.
     * Indices:
     *   0..143 : Inside numbers grid (24 columns x 6 rows of coordinate points)
     *   144    : Zero (0)
     *   145    : 1st Dozen (1-12)
     *   146    : 2nd Dozen (13-24)
     *   147    : 3rd Dozen (25-36)
     *   148    : Low (1-18)
     *   149    : Even
     *   150    : Red (Red Diamond)
     *   151    : Black (Black Diamond)
     *   152    : Odd
     *   153    : High (19-36)
     *   154    : Column 3 (2-1 Top Row)
     *   155    : Column 2 (2-1 Mid Row)
     *   156    : Column 1 (2-1 Bot Row)
     */
    public static function getAllBetPositions(): array
    {
        if (self::$allPositions !== null) {
            return self::$allPositions;
        }

        $positions = [];

        // Col 0: Border dividing Zero and Column 1
        $positions[0] = ['id' => 0, 'code' => 'SPLIT_0_3', 'name' => 'Split 0-3', 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [0, 3], 'col' => 0, 'row' => 0];
        $positions[1] = ['id' => 1, 'code' => 'TRIO_0_2_3', 'name' => 'Trio 0-2-3', 'type' => 'Street', 'multiplier' => 11.0, 'numbers' => [0, 2, 3], 'col' => 0, 'row' => 1];
        $positions[2] = ['id' => 2, 'code' => 'SPLIT_0_2', 'name' => 'Split 0-2', 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [0, 2], 'col' => 0, 'row' => 2];
        $positions[3] = ['id' => 3, 'code' => 'TRIO_0_1_2', 'name' => 'Trio 0-1-2', 'type' => 'Street', 'multiplier' => 11.0, 'numbers' => [0, 1, 2], 'col' => 0, 'row' => 3];
        $positions[4] = ['id' => 4, 'code' => 'SPLIT_0_1', 'name' => 'Split 0-1', 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [0, 1], 'col' => 0, 'row' => 4];
        $positions[5] = ['id' => 5, 'code' => 'FIRST_FOUR_0_1_2_3', 'name' => 'First Four 0-1-2-3', 'type' => 'Corner', 'multiplier' => 8.0, 'numbers' => [0, 1, 2, 3], 'col' => 0, 'row' => 5];

        for ($c = 1; $c <= 12; $c++) {
            $n1 = 3 * $c;       // Top (Row 1)
            $n2 = 3 * $c - 1;   // Middle (Row 2)
            $n3 = 3 * $c - 2;   // Bottom (Row 3)

            // Inside Column c (k = 2c - 1)
            $colK = 2 * $c - 1;
            $base = $colK * 6;
            $positions[$base + 0] = ['id' => $base + 0, 'code' => "STRAIGHT_$n1", 'name' => "Straight Up $n1", 'type' => 'Straight Up', 'multiplier' => 35.0, 'numbers' => [$n1], 'col' => $colK, 'row' => 0];
            $positions[$base + 1] = ['id' => $base + 1, 'code' => "SPLIT_{$n2}_{$n1}", 'name' => "Split $n2-$n1", 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [$n2, $n1], 'col' => $colK, 'row' => 1];
            $positions[$base + 2] = ['id' => $base + 2, 'code' => "STRAIGHT_$n2", 'name' => "Straight Up $n2", 'type' => 'Straight Up', 'multiplier' => 35.0, 'numbers' => [$n2], 'col' => $colK, 'row' => 2];
            $positions[$base + 3] = ['id' => $base + 3, 'code' => "SPLIT_{$n3}_{$n2}", 'name' => "Split $n3-$n2", 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [$n3, $n2], 'col' => $colK, 'row' => 3];
            $positions[$base + 4] = ['id' => $base + 4, 'code' => "STRAIGHT_$n3", 'name' => "Straight Up $n3", 'type' => 'Straight Up', 'multiplier' => 35.0, 'numbers' => [$n3], 'col' => $colK, 'row' => 4];
            $positions[$base + 5] = ['id' => $base + 5, 'code' => "STREET_{$n3}_{$n2}_{$n1}", 'name' => "Street $n3-$n2-$n1", 'type' => 'Street', 'multiplier' => 11.0, 'numbers' => [$n3, $n2, $n1], 'col' => $colK, 'row' => 5];

            // Divider Column between c and c + 1 (k = 2c)
            if ($c < 12) {
                $nextC = $c + 1;
                $m1 = 3 * $nextC;
                $m2 = 3 * $nextC - 1;
                $m3 = 3 * $nextC - 2;

                $divK = 2 * $c;
                $divBase = $divK * 6;
                $positions[$divBase + 0] = ['id' => $divBase + 0, 'code' => "SPLIT_{$n1}_{$m1}", 'name' => "Split $n1-$m1", 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [$n1, $m1], 'col' => $divK, 'row' => 0];
                $positions[$divBase + 1] = ['id' => $divBase + 1, 'code' => "CORNER_{$n2}_{$n1}_{$m2}_{$m1}", 'name' => "Corner $n2-$n1-$m2-$m1", 'type' => 'Corner', 'multiplier' => 8.0, 'numbers' => [$n2, $n1, $m2, $m1], 'col' => $divK, 'row' => 1];
                $positions[$divBase + 2] = ['id' => $divBase + 2, 'code' => "SPLIT_{$n2}_{$m2}", 'name' => "Split $n2-$m2", 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [$n2, $m2], 'col' => $divK, 'row' => 2];
                $positions[$divBase + 3] = ['id' => $divBase + 3, 'code' => "CORNER_{$n3}_{$n2}_{$m3}_{$m2}", 'name' => "Corner $n3-$n2-$m3-$m2", 'type' => 'Corner', 'multiplier' => 8.0, 'numbers' => [$n3, $n2, $m3, $m2], 'col' => $divK, 'row' => 3];
                $positions[$divBase + 4] = ['id' => $divBase + 4, 'code' => "SPLIT_{$n3}_{$m3}", 'name' => "Split $n3-$m3", 'type' => 'Split', 'multiplier' => 17.0, 'numbers' => [$n3, $m3], 'col' => $divK, 'row' => 4];
                $positions[$divBase + 5] = ['id' => $divBase + 5, 'code' => "LINE_{$n3}_{$m1}", 'name' => "Six Line $n3-$m1", 'type' => 'Line', 'multiplier' => 5.0, 'numbers' => [$n3, $n2, $n1, $m3, $m2, $m1], 'col' => $divK, 'row' => 5];
            }
        }

        // Special & Outside Bets (144 to 156)
        $positions[144] = ['id' => 144, 'code' => 'STRAIGHT_0', 'name' => 'Straight Up 0', 'type' => 'Straight Up', 'multiplier' => 35.0, 'numbers' => [0], 'special' => 'zero'];
        $positions[145] = ['id' => 145, 'code' => 'DOZEN_1', 'name' => '1-12', 'type' => 'Dozen', 'multiplier' => 2.0, 'numbers' => range(1, 12), 'special' => 'dozen'];
        $positions[146] = ['id' => 146, 'code' => 'DOZEN_2', 'name' => '13-24', 'type' => 'Dozen', 'multiplier' => 2.0, 'numbers' => range(13, 24), 'special' => 'dozen'];
        $positions[147] = ['id' => 147, 'code' => 'DOZEN_3', 'name' => '25-36', 'type' => 'Dozen', 'multiplier' => 2.0, 'numbers' => range(25, 36), 'special' => 'dozen'];
        $positions[148] = ['id' => 148, 'code' => 'LOW', 'name' => '1-18', 'type' => 'Low', 'multiplier' => 1.0, 'numbers' => range(1, 18), 'special' => 'even_money'];
        $positions[149] = ['id' => 149, 'code' => 'EVEN', 'name' => 'EVEN', 'type' => 'Even', 'multiplier' => 1.0, 'numbers' => array_values(array_filter(range(1, 36), fn($n) => $n % 2 === 0)), 'special' => 'even_money'];
        $positions[150] = ['id' => 150, 'code' => 'RED', 'name' => 'RED', 'type' => 'Red', 'multiplier' => 1.0, 'numbers' => self::RED_NUMBERS, 'special' => 'even_money'];
        $positions[151] = ['id' => 151, 'code' => 'BLACK', 'name' => 'BLACK', 'type' => 'Black', 'multiplier' => 1.0, 'numbers' => self::BLACK_NUMBERS, 'special' => 'even_money'];
        $positions[152] = ['id' => 152, 'code' => 'ODD', 'name' => 'ODD', 'type' => 'Odd', 'multiplier' => 1.0, 'numbers' => array_values(array_filter(range(1, 36), fn($n) => $n % 2 !== 0)), 'special' => 'even_money'];
        $positions[153] = ['id' => 153, 'code' => 'HIGH', 'name' => '19-36', 'type' => 'High', 'multiplier' => 1.0, 'numbers' => range(19, 36), 'special' => 'even_money'];
        $positions[154] = ['id' => 154, 'code' => 'COL_3', 'name' => '2-1 (Row 1)', 'type' => 'Column', 'multiplier' => 2.0, 'numbers' => self::COLUMNS['COL_3'], 'special' => 'column'];
        $positions[155] = ['id' => 155, 'code' => 'COL_2', 'name' => '2-1 (Row 2)', 'type' => 'Column', 'multiplier' => 2.0, 'numbers' => self::COLUMNS['COL_2'], 'special' => 'column'];
        $positions[156] = ['id' => 156, 'code' => 'COL_1', 'name' => '2-1 (Row 3)', 'type' => 'Column', 'multiplier' => 2.0, 'numbers' => self::COLUMNS['COL_1'], 'special' => 'column'];

        self::$allPositions = $positions;
        return self::$allPositions;
    }

    /**
     * Map a single roulette number (0 to 36) to its corresponding 0..156 position index.
     */
    public static function numberToPositionIndex(int $number): int
    {
        if ($number === 0) return 144;
        if ($number < 1 || $number > 36) return 144;

        if ($number % 3 === 0) {
            $c = $number / 3;
            return (int)((2 * $c - 1) * 6 + 0);
        } elseif ($number % 3 === 2) {
            $c = ($number + 1) / 3;
            return (int)((2 * $c - 1) * 6 + 2);
        } else {
            $c = ($number + 2) / 3;
            return (int)((2 * $c - 1) * 6 + 4);
        }
    }

    /**
     * Determine the colour of a roulette number.
     */
    public static function getColor(int $number): string
    {
        if ($number === 0) {
            return 'green';
        }
        return in_array($number, self::RED_NUMBERS, true) ? 'red' : 'black';
    }

    /**
     * Get winning categories and attributes for a winning number.
     */
    public static function evaluateWinningAttributes(int $number): array
    {
        $color = self::getColor($number);

        $attributes = [
            'number'  => $number,
            'colour'  => $color,
            'is_even' => $number > 0 && ($number % 2 === 0),
            'is_odd'  => $number > 0 && ($number % 2 !== 0),
            'is_low'  => $number >= 1 && $number <= 18,
            'is_high' => $number >= 19 && $number <= 36,
            'dozen'   => null,
            'column'  => null,
            'calls'   => [],
        ];

        if ($number > 0) {
            if ($number <= 12) {
                $attributes['dozen'] = 'DOZEN_1';
            } elseif ($number <= 24) {
                $attributes['dozen'] = 'DOZEN_2';
            } else {
                $attributes['dozen'] = 'DOZEN_3';
            }

            foreach (self::COLUMNS as $colKey => $numbers) {
                if (in_array($number, $numbers, true)) {
                    $attributes['column'] = $colKey;
                    break;
                }
            }
        }

        foreach (self::CALL_BETS as $callName => $numbers) {
            if (in_array($number, $numbers, true)) {
                $attributes['calls'][] = $callName;
            }
        }

        return $attributes;
    }

    /**
     * Get multiplier for a bet position/type from DB or fallback defaults.
     */
    public static function getMultiplier(string $betType): float
    {
        static $payouts = null;
        if ($payouts === null) {
            $payouts = PayoutRule::where('game_type_id', 3)->pluck('payout_multiplier', 'bet_position')->toArray();
        }

        $typeKey = strtoupper($betType);
        if (isset($payouts[$typeKey])) {
            return (float) $payouts[$typeKey];
        }

        // Fallbacks matching client specs
        return match ($typeKey) {
            'STRAIGHT', 'STRAIGHT UP'     => 35.0,
            'SPLIT'                       => 17.0,
            'STREET', 'TRIO'              => 11.0,
            'CORNER', 'FIRST FOUR'        => 8.0,
            'LINE'                        => 5.0,
            'COLUMN', 'COL'               => 2.0,
            'DOZEN'                       => 2.0,
            'RED', 'BLACK'                => 1.0,
            'EVEN', 'ODD'                 => 1.0,
            'LOW', 'HIGH', '1-18', '19-36'=> 1.0,
            '0-SPIEL', 'SERIES 0/2/3', 'ORPHELINS', 'SERIES 5/8', 'CROWN' => 2.0,
            default                       => 1.0,
        };
    }

    /**
     * Resolve a position index (0 to 156) from raw input.
     */
    public static function resolvePositionIndex(mixed $rawPos, ?string $type = null): ?int
    {
        $all = self::getAllBetPositions();

        // 1. Explicit integer index in range 0..156
        if (is_int($rawPos) && $rawPos >= 0 && $rawPos <= 156) {
            return $rawPos;
        }

        // 2. Numeric string in range 0..156
        if (is_numeric($rawPos)) {
            $intVal = (int)$rawPos;
            // If it's 0 to 36, check if user meant the single roulette number
            if ($intVal >= 0 && $intVal <= 36) {
                // If type indicates straight or default
                if ($type === 'Straight Up' || $type === 'STRAIGHT' || empty($type)) {
                    return self::numberToPositionIndex($intVal);
                }
            }
            if ($intVal >= 0 && $intVal <= 156) {
                return $intVal;
            }
        }

        $upper = strtoupper(trim((string)$rawPos));

        // 3. Named Aliases
        return match ($upper) {
            '0', 'STRAIGHT_0'                     => 144,
            'RED'                                 => 150,
            'BLACK'                               => 151,
            'EVEN'                                => 149,
            'ODD'                                 => 152,
            '1-18', 'LOW', 'LOW_1_18'             => 148,
            '19-36', 'HIGH', 'HIGH_19_36'         => 153,
            '1-12', 'DOZEN_1', 'DOZ_1'            => 145,
            '13-24', 'DOZEN_2', 'DOZ_2'           => 146,
            '25-36', 'DOZEN_3', 'DOZ_3'           => 147,
            'COL_3', 'COLUMN_3', '2-1_ROW_1'      => 154,
            'COL_2', 'COLUMN_2', '2-1_ROW_2'      => 155,
            'COL_1', 'COLUMN_1', '2-1_ROW_3'      => 156,
            default                               => null,
        };
    }

    /**
     * Evaluate a single bet against the winning number.
     *
     * @param array $bet ['position' => ..., 'amount' => ..., 'type' => optional, 'numbers' => optional, 'position_index' => optional]
     * @param int $winningNumber
     * @return array Enriched bet outcome
     */
    public static function evaluateBet(array $bet, int $winningNumber): array
    {
        $rawPos = $bet['position'] ?? ($bet['position_index'] ?? '');
        $amount = (float)($bet['amount'] ?? 0);
        $all    = self::getAllBetPositions();

        // 1. Resolve to exact 0..156 position index if possible
        $posIdx = isset($bet['position_index']) && is_numeric($bet['position_index'])
            ? (int)$bet['position_index']
            : self::resolvePositionIndex($rawPos, $bet['type'] ?? null);

        if ($posIdx !== null && isset($all[$posIdx])) {
            $def        = $all[$posIdx];
            $isWin      = in_array($winningNumber, $def['numbers'], true);
            $multiplier = (float)$def['multiplier'];
            $winAmount  = $isWin ? round($amount + ($amount * $multiplier), 2) : 0.00;

            return [
                'position_index' => $posIdx,
                'position'       => (string)$rawPos,
                'label'          => $bet['label'] ?? $def['name'],
                'rule_type'      => $def['type'],
                'amount'         => $amount,
                'is_win'         => $isWin,
                'multiplier'     => $multiplier,
                'win_amount'     => $winAmount,
                'numbers'        => $def['numbers'],
                'col'            => $def['col'] ?? null,
                'row'            => $def['row'] ?? null,
                'special'        => $def['special'] ?? null,
            ];
        }

        // 2. Fallback for Call Bets or custom string codes
        $strPos = strtoupper(trim((string)$rawPos));
        $attr   = self::evaluateWinningAttributes($winningNumber);
        $isWin  = false;
        $multiplier = 1.0;
        $ruleType = 'CUSTOM';
        $covered = $bet['numbers'] ?? [];

        if (isset(self::CALL_BETS[$strPos])) {
            $ruleType   = $strPos;
            $multiplier = self::getMultiplier($strPos);
            $covered    = self::CALL_BETS[$strPos];
            $isWin      = in_array($winningNumber, $covered, true);
        } elseif (!empty($covered)) {
            $isWin      = in_array($winningNumber, $covered, true);
            $multiplier = (float)($bet['multiplier'] ?? 1.0);
        }

        $winAmount = $isWin ? round($amount + ($amount * $multiplier), 2) : 0.00;

        return [
            'position_index' => null,
            'position'       => $strPos,
            'label'          => $bet['label'] ?? $strPos,
            'rule_type'      => $ruleType,
            'amount'         => $amount,
            'is_win'         => $isWin,
            'multiplier'     => $multiplier,
            'win_amount'     => $winAmount,
            'numbers'        => $covered,
            'col'            => null,
            'row'            => null,
            'special'        => null,
        ];
    }

    /**
     * Settle a roulette round for a table and multiple tabs.
     *
     * @param int|null $tableId Table/Station ID
     * @param string $gameNo Unique round code (e.g. "R-1001")
     * @param int $winningNumber Outcome (0 - 36)
     * @param array $tabBets Map of [tab_id => [bet1, bet2, ...]] or list of bets with tab_id
     * @return array Created history records
     */
    public function settleRound(?int $tableId, string $gameNo, int $winningNumber, array $tabBets): array
    {
        if ($winningNumber < 0 || $winningNumber > 36) {
            throw new InvalidArgumentException("Winning number must be between 0 and 36");
        }

        $winningColour = self::getColor($winningNumber);
        $now = now();

        // Normalize tab bets into [tab_id => [bet1, bet2, ...]]
        $normalizedTabs = [];
        foreach ($tabBets as $key => $bets) {
            if (isset($bets['tab_id']) && isset($bets['bets'])) {
                $tId = (string)$bets['tab_id'];
                $normalizedTabs[$tId] = $bets['bets'];
            } elseif (is_string($key) || is_numeric($key)) {
                $tId = (string)$key;
                $normalizedTabs[$tId] = is_array($bets) ? $bets : [$bets];
            }
        }

        // Build global snapshot of all bets on the board across tabs
        $roundWideBets = [];
        foreach ($normalizedTabs as $tId => $bets) {
            foreach ($bets as $b) {
                $eval = self::evaluateBet($b, $winningNumber);
                $eval['tab_id'] = $tId;
                $roundWideBets[] = $eval;
            }
        }

        $historyRecords = [];

        DB::transaction(function () use (
            $tableId,
            $gameNo,
            $winningNumber,
            $winningColour,
            $normalizedTabs,
            $roundWideBets,
            $now,
            &$historyRecords
        ) {
            foreach ($normalizedTabs as $tabIdentifier => $bets) {
                $evaluatedBets = [];
                $totalBet = 0.0;
                $totalWin = 0.0;
                $coveredNumbers = [];

                foreach ($bets as $b) {
                    $res = self::evaluateBet($b, $winningNumber);
                    $evaluatedBets[] = $res;
                    $totalBet += $res['amount'];
                    $totalWin += $res['win_amount'];
                    if (!empty($res['numbers'])) {
                        $coveredNumbers = array_merge($coveredNumbers, $res['numbers']);
                    }
                }
                $coveredNumbers = array_values(array_unique($coveredNumbers));

                // Locate Tab unambiguously
                $tab = null;
                if (is_numeric($tabIdentifier)) {
                    $tab = Tab::find((int)$tabIdentifier) ?? Tab::where('tab_number', (int)$tabIdentifier)->first();
                }
                if (!$tab) {
                    $tab = Tab::where('table_name', $tabIdentifier)
                        ->orWhere('tab_number', $tabIdentifier)
                        ->first();
                }

                $currentCredit = 0.00;

                if ($tab) {
                    $tabStatus = TabStatus::firstOrCreate(
                        ['tab_id' => $tab->id],
                        ['current_balance' => 0.00, 'shift_total_bet' => 0.00, 'shift_total_win' => 0.00]
                    );

                    // Update Tab Balance: Deduct Bet, Add Total Payout
                    $newBalance = (float)$tabStatus->current_balance - $totalBet + $totalWin;
                    if ($newBalance < 0) {
                        $newBalance = 0.00;
                    }

                    $tabStatus->current_balance   = $newBalance;
                    $tabStatus->shift_total_bet   = (float)$tabStatus->shift_total_bet + $totalBet;
                    $tabStatus->shift_total_win   = (float)$tabStatus->shift_total_win + $totalWin;
                    $tabStatus->shift_games_count = (int)$tabStatus->shift_games_count + 1;
                    $tabStatus->shift_last_bet    = $totalBet;
                    $tabStatus->last_bet_amount   = $totalBet;
                    $tabStatus->last_win_amount   = $totalWin;
                    $tabStatus->last_game_no      = $gameNo;
                    $tabStatus->last_game_at      = $now;
                    $tabStatus->last_synced_at    = $now;

                    // Update failure recovery snapshot
                    $recData = is_array($tabStatus->recovery_data) ? $tabStatus->recovery_data : [];
                    $recData['last_game_no']        = $gameNo;
                    $recData['last_winning_number'] = $winningNumber;
                    $recData['last_bets']           = $evaluatedBets;
                    $recData['balance_after']       = $newBalance;
                    $tabStatus->recovery_data       = $recData;

                    $tabStatus->save();

                    // If active session, update session aggregates
                    if ($tabStatus->current_session_id) {
                        $session = \App\Models\TabSession::find($tabStatus->current_session_id);
                        if ($session) {
                            $session->total_bet       = (float)$session->total_bet + $totalBet;
                            $session->total_win       = (float)$session->total_win + $totalWin;
                            $session->games_played    = (int)$session->games_played + 1;
                            $session->closing_balance = $newBalance;
                            $session->save();
                        }
                    }

                    $currentCredit = (float)$tabStatus->current_balance;
                }

                $record = RouletteHistory::create([
                    'table_id'       => $tableId ?? ($tab ? $tab->id : 1),
                    'game_no'        => $gameNo,
                    'tab_id'         => (string)$tabIdentifier,
                    'winning_number' => $winningNumber,
                    'winning_colour' => $winningColour,
                    'roulette_type'  => 'European',
                    'bet_position'   => $evaluatedBets,
                    'bet_numbers'    => $coveredNumbers,
                    'bet_amount'     => $totalBet,
                    'win_amount'     => $totalWin,
                    'current_credit' => $currentCredit,
                    'round_bets'     => $roundWideBets,
                    'date_time'      => $now,
                ]);

                $historyRecords[] = $record;
            }
        });

        return $historyRecords;
    }
}
