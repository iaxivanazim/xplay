<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameConfig;
use App\Models\GameConfigPayoutRule;
use App\Models\GameType;
use App\Models\PayoutRule;

class DefaultGameConfigSeeder extends Seeder
{
    public function run(): void
    {
        $bac = GameType::where('code', 'BAC')->first();
        $ab  = GameType::where('code', 'AB')->first();
        $rol = GameType::whereIn('code', ['ROL', 'ROU'])->first();

        // 1. European Roulette Default Config
        if ($rol) {
            $rolConfig = GameConfig::firstOrCreate(
                ['name' => 'Standard European Roulette'],
                [
                    'game_type_id'  => $rol->id,
                    'min_bet'       => '10|25|50',
                    'max_bet'       => '500|1000|2000',
                    'side_min_bet'  => 10.00,
                    'side_max_bet'  => 1000.00,
                    'roulette_type' => 'european',
                    'is_active'     => 1,
                    'is_default'    => 1,
                ]
            );

            // Attach all Roulette payout rules
            $rolRules = PayoutRule::where('game_type_id', $rol->id)->get();
            foreach ($rolRules as $rule) {
                GameConfigPayoutRule::firstOrCreate(
                    [
                        'game_config_id' => $rolConfig->id,
                        'payout_id'      => $rule->payout_id,
                    ],
                    [
                        'is_active'         => 1,
                        'payout_multiplier' => $rule->payout_multiplier,
                    ]
                );
            }
        }

        // 2. Baccarat Default Config
        if ($bac) {
            $bacConfig = GameConfig::firstOrCreate(
                ['name' => 'Standard Baccarat'],
                [
                    'game_type_id'          => $bac->id,
                    'min_bet'               => '10|50|100',
                    'max_bet'               => '500|2000|5000',
                    'side_min_bet'          => 10.00,
                    'side_max_bet'          => 1000.00,
                    'burn_card'             => 1,
                    'baccarat_6_commission' => 0,
                    'commission'            => 0.0500,
                    'tie_min'               => 10.00,
                    'tie_max'               => 500.00,
                    'pair_min'              => 10.00,
                    'pair_max'              => 500.00,
                    'super6_min'            => 10.00,
                    'super6_max'            => 500.00,
                    'is_active'             => 1,
                    'is_default'            => 1,
                ]
            );

            $bacRules = PayoutRule::where('game_type_id', $bac->id)->get();
            foreach ($bacRules as $rule) {
                GameConfigPayoutRule::firstOrCreate(
                    [
                        'game_config_id' => $bacConfig->id,
                        'payout_id'      => $rule->payout_id,
                    ],
                    [
                        'is_active'         => 1,
                        'payout_multiplier' => $rule->payout_multiplier,
                    ]
                );
            }
        }

        // 3. Andar Bahar Default Config
        if ($ab) {
            $abConfig = GameConfig::firstOrCreate(
                ['name' => 'Standard Andar Bahar'],
                [
                    'game_type_id' => $ab->id,
                    'min_bet'      => '10|50|100',
                    'max_bet'      => '500|2000|5000',
                    'side_min_bet' => 10.00,
                    'side_max_bet' => 1000.00,
                    'burn_card'    => 1,
                    'andar_min'    => 10.00,
                    'andar_max'    => 2500.00,
                    'bahar_min'    => 10.00,
                    'bahar_max'    => 2500.00,
                    'is_active'    => 1,
                    'is_default'   => 1,
                ]
            );

            $abRules = PayoutRule::where('game_type_id', $ab->id)->get();
            foreach ($abRules as $rule) {
                GameConfigPayoutRule::firstOrCreate(
                    [
                        'game_config_id' => $abConfig->id,
                        'payout_id'      => $rule->payout_id,
                    ],
                    [
                        'is_active'         => 1,
                        'payout_multiplier' => $rule->payout_multiplier,
                    ]
                );
            }
        }
    }
}
