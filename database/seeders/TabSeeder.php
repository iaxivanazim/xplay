<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\TabGameConfig;
use App\Models\GameConfig;
use App\Models\User;

class TabSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $adminId = $admin ? $admin->id : 1;

        $configs = GameConfig::all();
        $defaultConfig = $configs->first();

        // 42 machines configuration matching screenshots
        $machines = [
            // Row 1
            ['num' => 1,  'name' => 'Amatic-1',  'balance' => 0.80,   'in' => 890.00,  'out' => 750.13,  'games' => 5057, 'last_bet' => 0.20],
            ['num' => 2,  'name' => 'Amatic-2',  'balance' => 0.00,   'in' => 380.00,  'out' => 120.00,  'games' => 2028, 'last_bet' => 0.20],
            ['num' => 3,  'name' => 'Amatic-3',  'balance' => 0.00,   'in' => 250.00,  'out' => 203.00,  'games' => 7072, 'last_bet' => 0.05],
            ['num' => 4,  'name' => 'Amatic-4',  'balance' => 0.00,   'in' => 40.00,   'out' => 0.00,    'games' => 360,  'last_bet' => 2.00],
            ['num' => 5,  'name' => 'Amatic-5',  'balance' => 0.00,   'in' => 374.00,  'out' => 328.20,  'games' => 1970, 'last_bet' => 0.16],
            ['num' => 6,  'name' => 'Amatic-6',  'balance' => 0.05,   'in' => 418.00,  'out' => 365.03,  'games' => 1429, 'last_bet' => 0.50],
            ['num' => 7,  'name' => 'Amatic-7',  'balance' => 0.00,   'in' => 407.00,  'out' => 502.12,  'games' => 1111, 'last_bet' => 1.00],

            // Row 2
            ['num' => 8,  'name' => 'Amatic-8',  'balance' => 0.00,   'in' => 185.00,  'out' => 55.75,   'games' => 483,  'last_bet' => 0.10],
            ['num' => 9,  'name' => 'Amatic-9',  'balance' => 0.00,   'in' => 560.00,  'out' => 220.17,  'games' => 1668, 'last_bet' => 1.00],
            ['num' => 10, 'name' => 'Amatic-10', 'balance' => 0.00,   'in' => 261.00,  'out' => 290.00,  'games' => 1946, 'last_bet' => 10.00],
            ['num' => 11, 'name' => 'Amatic-11', 'balance' => 0.00,   'in' => 510.00,  'out' => 105.05,  'games' => 800,  'last_bet' => 3.75],
            ['num' => 12, 'name' => 'Amatic-12', 'balance' => 0.00,   'in' => 210.00,  'out' => 5.00,    'games' => 879,  'last_bet' => 5.00],
            ['num' => 13, 'name' => 'Apex-13',   'balance' => 0.06,   'in' => 356.00,  'out' => 840.10,  'games' => 1748, 'last_bet' => 0.50],
            ['num' => 14, 'name' => 'Apex-14',   'balance' => 0.00,   'in' => 170.00,  'out' => 230.95,  'games' => 3293, 'last_bet' => 0.40],

            // Row 3
            ['num' => 15, 'name' => 'Apex-15',   'balance' => 0.00,   'in' => 787.00,  'out' => 1406.56, 'games' => 2939, 'last_bet' => 0.20],
            ['num' => 18, 'name' => 'Apex-18',   'balance' => 0.00,   'in' => 376.00,  'out' => 197.15,  'games' => 1333, 'last_bet' => 0.60],
            ['num' => 19, 'name' => 'Apex-19',   'balance' => 0.00,   'in' => 0.00,    'out' => 0.00,    'games' => 0,    'last_bet' => 0.25],
            ['num' => 20, 'name' => 'Apex-20',   'balance' => 0.00,   'in' => 60.00,   'out' => 0.00,    'games' => 805,  'last_bet' => 0.20],
            ['num' => 21, 'name' => 'Apex-21',   'balance' => 0.00,   'in' => 170.00,  'out' => 0.00,    'games' => 147,  'last_bet' => 2.00],
            ['num' => 22, 'name' => 'Apex-22',   'balance' => 0.00,   'in' => 1605.00, 'out' => 2021.30, 'games' => 1457, 'last_bet' => 1.00],
            ['num' => 23, 'name' => 'Apex-23',   'balance' => 0.00,   'in' => 450.00,  'out' => 320.00,  'games' => 650,  'last_bet' => 0.50],

            // Row 4
            ['num' => 24, 'name' => 'Apex-24',   'balance' => 0.00,   'in' => 120.00,  'out' => 90.00,   'games' => 410,  'last_bet' => 0.20],
            ['num' => 25, 'name' => 'Apex-25',   'balance' => 0.00,   'in' => 290.00,  'out' => 250.00,  'games' => 890,  'last_bet' => 0.50],
            ['num' => 26, 'name' => 'Apex-26',   'balance' => 0.00,   'in' => 330.00,  'out' => 180.00,  'games' => 740,  'last_bet' => 1.00],
            ['num' => 27, 'name' => 'Apex-27',   'balance' => 0.00,   'in' => 500.00,  'out' => 450.00,  'games' => 1200, 'last_bet' => 0.25],
            ['num' => 28, 'name' => 'Amatic-28', 'balance' => 0.00,   'in' => 410.00,  'out' => 380.00,  'games' => 960,  'last_bet' => 0.50],
            ['num' => 29, 'name' => 'Amatic-29', 'balance' => 0.00,   'in' => 150.00,  'out' => 110.00,  'games' => 320,  'last_bet' => 0.20],
            ['num' => 30, 'name' => 'Amatic-30', 'balance' => 0.08,   'in' => 680.00,  'out' => 540.00,  'games' => 1580, 'last_bet' => 0.10],

            // Row 5
            ['num' => 31, 'name' => 'Amatic-31', 'balance' => 0.05,   'in' => 520.00,  'out' => 430.00,  'games' => 1100, 'last_bet' => 0.50],
            ['num' => 32, 'name' => 'Amatic-32', 'balance' => 0.00,   'in' => 210.00,  'out' => 190.00,  'games' => 480,  'last_bet' => 0.25],
            ['num' => 33, 'name' => 'Amatic-33', 'balance' => 5.33,   'in' => 750.00,  'out' => 610.00,  'games' => 1890, 'last_bet' => 0.50],
            ['num' => 34, 'name' => 'Amatic-34', 'balance' => 0.00,   'in' => 340.00,  'out' => 280.00,  'games' => 760,  'last_bet' => 0.20],
            ['num' => 35, 'name' => 'Amatic-35', 'balance' => 0.00,   'in' => 180.00,  'out' => 140.00,  'games' => 520,  'last_bet' => 0.10],
            ['num' => 36, 'name' => 'Amatic-36', 'balance' => 0.30,   'in' => 890.00,  'out' => 780.00,  'games' => 2100, 'last_bet' => 1.00],
            ['num' => 37, 'name' => 'Amatic-37', 'balance' => 0.00,   'in' => 260.00,  'out' => 210.00,  'games' => 640,  'last_bet' => 0.25],

            // Row 6
            ['num' => 38, 'name' => 'Amatic-38', 'balance' => 161.38, 'in' => 1250.00, 'out' => 800.00,  'games' => 2450, 'last_bet' => 5.00],
            ['num' => 39, 'name' => 'Amatic-39', 'balance' => 0.00,   'in' => 310.00,  'out' => 290.00,  'games' => 830,  'last_bet' => 0.50],
            ['num' => 40, 'name' => 'VIP-40',    'balance' => 0.00,   'in' => 1500.00, 'out' => 1200.00, 'games' => 1100, 'last_bet' => 10.00],
            ['num' => 41, 'name' => 'VIP-41',    'balance' => 0.00,   'in' => 950.00,  'out' => 800.00,  'games' => 920,  'last_bet' => 5.00],
            ['num' => 42, 'name' => 'VIP-42',    'balance' => 300.00, 'in' => 2000.00, 'out' => 1400.00, 'games' => 1750, 'last_bet' => 20.00],
            ['num' => 43, 'name' => 'VIP-43',    'balance' => 0.00,   'in' => 1200.00, 'out' => 950.00,  'games' => 1340, 'last_bet' => 10.00],
            ['num' => 44, 'name' => 'VIP-44',    'balance' => 0.00,   'in' => 1100.00, 'out' => 850.00,  'games' => 980,  'last_bet' => 5.00],
        ];

        foreach ($machines as $m) {
            $mac = sprintf('00:E0:4C:68:%02X:%02X', intval($m['num'] / 256), $m['num'] % 256);
            $ip  = '192.168.1.' . (100 + $m['num']);

            $tab = Tab::firstOrCreate(
                ['tab_number' => $m['num']],
                [
                    'table_name'        => $m['name'],
                    'game_type_id'      => $defaultConfig?->game_type_id,
                    'game_config_id'    => $defaultConfig?->id,
                    'active_mac'        => $mac,
                    'ip_address'        => $ip,
                    'denomination'      => 0.0100,
                    'status'            => 1,
                    'lock_status'       => 'unlocked',
                    'connection_status' => 'online',
                    'last_seen_at'      => now(),
                ]
            );

            // Assign all default game configs to this tab
            foreach ($configs as $cfg) {
                TabGameConfig::firstOrCreate(
                    [
                        'tab_id'         => $tab->id,
                        'game_config_id' => $cfg->id,
                    ],
                    [
                        'is_active'   => 1,
                        'assigned_by' => $adminId,
                        'assigned_at' => now(),
                    ]
                );
            }

            // Create or update TabStatus
            $hasSession = in_array($m['num'], [38, 42]);
            $otp = $hasSession ? str_pad(strval(rand(100000, 999999)), 6, '0', STR_PAD_LEFT) : null;
            $sessionId = null;

            if ($hasSession) {
                $session = TabSession::create([
                    'tab_id'          => $tab->id,
                    'otp'             => $otp,
                    'player_name'     => $m['num'] === 38 ? 'John Doe' : 'VIP High Roller',
                    'player_phone'    => $m['num'] === 38 ? '+1 555-0199' : '+1 555-0777',
                    'player_card_id'  => $m['num'] === 38 ? 'CARD-9921' : 'VIP-CARD-001',
                    'buyin_amount'    => $m['balance'] + 100,
                    'cashout_amount'  => 0,
                    'opening_balance' => $m['balance'],
                    'closing_balance' => 0,
                    'total_bet'       => $m['games'] * $m['last_bet'],
                    'total_win'       => ($m['games'] * $m['last_bet']) - 50,
                    'games_played'    => $m['games'],
                    'opened_by'       => $adminId,
                    'status'          => 'active',
                    'opened_at'       => now()->subHours(2),
                ]);
                $sessionId = $session->session_id;
            }

            TabStatus::updateOrCreate(
                ['tab_id' => $tab->id],
                [
                    'status'             => $hasSession ? 'active' : 'idle',
                    'lock_status'        => 'unlocked',
                    'current_session_id' => $sessionId,
                    'current_otp'        => $otp,
                    'player_name'        => $hasSession ? ($m['num'] === 38 ? 'John Doe' : 'VIP High Roller') : null,
                    'player_phone'       => $hasSession ? ($m['num'] === 38 ? '+1 555-0199' : '+1 555-0777') : null,
                    'player_card_id'     => $hasSession ? ($m['num'] === 38 ? 'CARD-9921' : 'VIP-CARD-001') : null,
                    'session_started_at' => $hasSession ? now()->subHours(2) : null,
                    'current_balance'    => $m['balance'],
                    'session_buyin'      => $hasSession ? ($m['balance'] + 100) : 0,
                    'session_cashout'    => 0,
                    'shift_total_in'     => $m['in'],
                    'shift_total_out'    => $m['out'],
                    'shift_total_bet'    => $m['games'] * $m['last_bet'],
                    'shift_total_win'    => ($m['games'] * $m['last_bet']) * 0.94,
                    'shift_games_count'  => $m['games'],
                    'shift_last_bet'     => $m['last_bet'],
                    'last_game_no'       => 'G-' . rand(10000, 99999),
                    'last_game_at'       => now()->subMinutes(rand(1, 60)),
                    'last_bet_amount'    => $m['last_bet'],
                    'last_win_amount'    => $m['balance'] > 0 ? rand(5, 50) : 0,
                    'recovery_data'      => [
                        'tab_id'         => $tab->id,
                        'tab_name'       => $m['name'],
                        'balance'        => $m['balance'],
                        'otp'            => $otp,
                        'player'         => $hasSession ? ($m['num'] === 38 ? 'John Doe' : 'VIP High Roller') : null,
                        'active_game_id' => $defaultConfig?->id,
                        'timestamp'      => now()->toIso8601String(),
                    ],
                    'last_synced_at'     => now(),
                    'connection_status'  => 'online',
                    'active_mac'         => $mac,
                ]
            );
        }
    }
}
