<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayoutRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Scope: Virtuals tab-based games only — Baccarat (type 1), Andar Bahar (type 2), Roulette (type 3).
     */
    public function run(): void
    {
        DB::table('payout_rules')->insert([

            /* ── GAME TYPE 1: BACCARAT ───────────────────────────────── */

            ['payout_id' => 1,  'game_type_id' => 1, 'bet_name' => 'Player',           'bet_position' => 'P',    'payout_multiplier' => 1,    'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 2,  'game_type_id' => 1, 'bet_name' => 'Banker',           'bet_position' => 'B',    'payout_multiplier' => 0.95, 'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 3,  'game_type_id' => 1, 'bet_name' => 'Tie',              'bet_position' => 'T',    'payout_multiplier' => 8,    'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 4,  'game_type_id' => 1, 'bet_name' => 'Player Pair',      'bet_position' => 'PP',   'payout_multiplier' => 11,   'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 5,  'game_type_id' => 1, 'bet_name' => 'Banker Pair',      'bet_position' => 'BP',   'payout_multiplier' => 11,   'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 6,  'game_type_id' => 1, 'bet_name' => 'Lucky 6 2 Cards',  'bet_position' => 'S6*2', 'payout_multiplier' => 12,   'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 7,  'game_type_id' => 1, 'bet_name' => 'Lucky 6 3 Cards',  'bet_position' => 'S6*3', 'payout_multiplier' => 20,   'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 8,  'game_type_id' => 1, 'bet_name' => 'Baccarat 6',       'bet_position' => 'B6',   'payout_multiplier' => 0.95, 'is_active' => 1, 'is_jackpot' => 0],


            /* ── GAME TYPE 2: ANDAR BAHAR ────────────────────────────── */

            ['payout_id' => 9,  'game_type_id' => 2, 'bet_name' => 'Andar',           'bet_position' => 'A',  'payout_multiplier' => 1,    'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 10, 'game_type_id' => 2, 'bet_name' => 'Bahar',           'bet_position' => 'B',  'payout_multiplier' => 1,    'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 11, 'game_type_id' => 2, 'bet_name' => 'Andar 1st Shot',  'bet_position' => 'A1', 'payout_multiplier' => 0.25, 'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 12, 'game_type_id' => 2, 'bet_name' => 'Bahar 1st Shot',  'bet_position' => 'B1', 'payout_multiplier' => 0.25, 'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 13, 'game_type_id' => 2, 'bet_name' => 'Super Andar',     'bet_position' => 'SA', 'payout_multiplier' => 11,   'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 14, 'game_type_id' => 2, 'bet_name' => 'Super Bahar',     'bet_position' => 'SB', 'payout_multiplier' => 11,   'is_active' => 1, 'is_jackpot' => 0],


            /* ── GAME TYPE 3: ROULETTE ───────────────────────────────── */
            /*  European Roulette — payouts confirmed by client            */
            /*                                                             */
            /*  bet_name          bet_position   payout_multiplier         */
            /*  ────────────────  ─────────────  ─────────────────         */
            /*  Straight Up       STRAIGHT       35:1                      */
            /*  Split             SPLIT          17:1                      */
            /*  Street            STREET         11:1                      */
            /*  Corner            CORNER         8:1                       */
            /*  Line              LINE           5:1                       */
            /*  Column            COLUMN         2:1                       */
            /*  Dozen             DOZEN          2:1                       */
            /*  Red               RED            1:1                       */
            /*  Black             BLACK          1:1                       */
            /*  Even              EVEN           1:1                       */
            /*  Odd               ODD            1:1                       */
            /*  Low (1-18)        LOW            1:1                       */
            /*  High (19-36)      HIGH           1:1                       */

            ['payout_id' => 15, 'game_type_id' => 3, 'bet_name' => 'Straight Up',  'bet_position' => 'STRAIGHT', 'payout_multiplier' => 35, 'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 16, 'game_type_id' => 3, 'bet_name' => 'Split',        'bet_position' => 'SPLIT',    'payout_multiplier' => 17, 'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 17, 'game_type_id' => 3, 'bet_name' => 'Street',       'bet_position' => 'STREET',   'payout_multiplier' => 11, 'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 18, 'game_type_id' => 3, 'bet_name' => 'Corner',       'bet_position' => 'CORNER',   'payout_multiplier' => 8,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 19, 'game_type_id' => 3, 'bet_name' => 'Line',         'bet_position' => 'LINE',     'payout_multiplier' => 5,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 20, 'game_type_id' => 3, 'bet_name' => 'Column',       'bet_position' => 'COLUMN',   'payout_multiplier' => 2,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 21, 'game_type_id' => 3, 'bet_name' => 'Dozen',        'bet_position' => 'DOZEN',    'payout_multiplier' => 2,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 22, 'game_type_id' => 3, 'bet_name' => 'Red',          'bet_position' => 'RED',      'payout_multiplier' => 1,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 23, 'game_type_id' => 3, 'bet_name' => 'Black',        'bet_position' => 'BLACK',    'payout_multiplier' => 1,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 24, 'game_type_id' => 3, 'bet_name' => 'Even',         'bet_position' => 'EVEN',     'payout_multiplier' => 1,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 25, 'game_type_id' => 3, 'bet_name' => 'Odd',          'bet_position' => 'ODD',      'payout_multiplier' => 1,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 26, 'game_type_id' => 3, 'bet_name' => 'Low (1-18)',   'bet_position' => 'LOW',      'payout_multiplier' => 1,  'is_active' => 1, 'is_jackpot' => 0],
            ['payout_id' => 27, 'game_type_id' => 3, 'bet_name' => 'High (19-36)', 'bet_position' => 'HIGH',     'payout_multiplier' => 1,  'is_active' => 1, 'is_jackpot' => 0],

        ]);
    }
}
