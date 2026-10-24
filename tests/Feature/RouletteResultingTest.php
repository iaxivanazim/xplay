<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\RouletteHistory;
use App\Services\RouletteEngine;

class RouletteResultingTest extends TestCase
{
    protected function getAdmin()
    {
        $adminRole = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $perm = \App\Models\Permission::firstOrCreate(['slug' => 'view-history'], ['name' => 'View History', 'module' => 'history']);
        if (!$adminRole->permissions()->where('permissions.id', $perm->id)->exists()) {
            $adminRole->permissions()->attach($perm->id);
        }
        $admin = User::where('role_id', $adminRole->id)->first();
        if (!$admin) {
            $admin = User::first();
            if ($admin) {
                $admin->update(['role_id' => $adminRole->id]);
            } else {
                $admin = User::factory()->create(['role_id' => $adminRole->id]);
            }
        }
        return $admin;
    }

    public function test_roulette_engine_evaluates_payouts_accurately()
    {
        // 17 is Black, Odd, Low (1-18), Dozen 2 (13-24), Col 2 (Row 2), in Orphelins & Voisins
        $winningNum = 17;

        // Straight Up 17: $100 -> Payout: 100 + (100 * 35) = $3,600
        $resStraight = RouletteEngine::evaluateBet(['position' => '17', 'amount' => 100], $winningNum);
        $this->assertTrue($resStraight['is_win']);
        $this->assertEquals(35.0, (float)$resStraight['multiplier']);
        $this->assertEquals(3600.0, (float)$resStraight['win_amount']);

        // Black Even Money: $50 -> Payout: 50 + (50 * 1) = $100
        $resBlack = RouletteEngine::evaluateBet(['position' => 'BLACK', 'amount' => 50], $winningNum);
        $this->assertTrue($resBlack['is_win']);
        $this->assertEquals(100.0, (float)$resBlack['win_amount']);

        // Red Even Money: $50 -> Lost -> $0
        $resRed = RouletteEngine::evaluateBet(['position' => 'RED', 'amount' => 50], $winningNum);
        $this->assertFalse($resRed['is_win']);
        $this->assertEquals(0.0, (float)$resRed['win_amount']);

        // Dozen 2: $100 -> Payout: 100 + (100 * 2) = $300
        $resDozen = RouletteEngine::evaluateBet(['position' => 'DOZEN_2', 'amount' => 100], $winningNum);
        $this->assertTrue($resDozen['is_win']);
        $this->assertEquals(300.0, (float)$resDozen['win_amount']);
    }

    public function test_roulette_engine_supports_all_156_bet_positions()
    {
        $all = RouletteEngine::getAllBetPositions();
        $this->assertCount(157, $all); // Indices 0 to 156

        // Position 144: Zero
        $resZero = RouletteEngine::evaluateBet(['position_index' => 144, 'amount' => 100], 0);
        $this->assertTrue($resZero['is_win']);
        $this->assertEquals(35.0, (float)$resZero['multiplier']);
        $this->assertEquals(3600.0, (float)$resZero['win_amount']);

        // Position 68: Straight Up 17
        $res68 = RouletteEngine::evaluateBet(['position_index' => 68, 'amount' => 50], 17);
        $this->assertTrue($res68['is_win']);
        $this->assertEquals(35.0, (float)$res68['multiplier']);
        $this->assertEquals(1800.0, (float)$res68['win_amount']);

        // Position 14: Split 2-5
        $resSplit = RouletteEngine::evaluateBet(['position_index' => 14, 'amount' => 50], 2);
        $this->assertTrue($resSplit['is_win']);
        $this->assertEquals(17.0, (float)$resSplit['multiplier']);
        $this->assertEquals(900.0, (float)$resSplit['win_amount']);

        // Position 15: Corner 1-2-4-5
        $resCorner = RouletteEngine::evaluateBet(['position_index' => 15, 'amount' => 50], 4);
        $this->assertTrue($resCorner['is_win']);
        $this->assertEquals(8.0, (float)$resCorner['multiplier']);
        $this->assertEquals(450.0, (float)$resCorner['win_amount']);

        // Position 11: Street 1-2-3
        $resStreet = RouletteEngine::evaluateBet(['position_index' => 11, 'amount' => 50], 3);
        $this->assertTrue($resStreet['is_win']);
        $this->assertEquals(11.0, (float)$resStreet['multiplier']);
        $this->assertEquals(600.0, (float)$resStreet['win_amount']);

        // Position 17: Six Line 1-6
        $resLine = RouletteEngine::evaluateBet(['position_index' => 17, 'amount' => 50], 6);
        $this->assertTrue($resLine['is_win']);
        $this->assertEquals(5.0, (float)$resLine['multiplier']);
        $this->assertEquals(300.0, (float)$resLine['win_amount']);

        // Position 145: Dozen 1 (1-12)
        $resDoz = RouletteEngine::evaluateBet(['position_index' => 145, 'amount' => 100], 12);
        $this->assertTrue($resDoz['is_win']);
        $this->assertEquals(2.0, (float)$resDoz['multiplier']);
        $this->assertEquals(300.0, (float)$resDoz['win_amount']);

        // Position 150: Red
        $resRed = RouletteEngine::evaluateBet(['position_index' => 150, 'amount' => 100], 1);
        $this->assertTrue($resRed['is_win']);
        $this->assertEquals(1.0, (float)$resRed['multiplier']);
        $this->assertEquals(200.0, (float)$resRed['win_amount']);

        // Position 156: Column 1 (Bottom Row)
        $resCol1 = RouletteEngine::evaluateBet(['position_index' => 156, 'amount' => 100], 34);
        $this->assertTrue($resCol1['is_win']);
        $this->assertEquals(2.0, (float)$resCol1['multiplier']);
        $this->assertEquals(300.0, (float)$resCol1['win_amount']);
    }

    public function test_station_middleware_receives_roulette_result_and_updates_tabs()
    {
        $tab = Tab::firstOrCreate(
            ['tab_number' => 101],
            ['table_name' => 'TAB-101', 'game_type_id' => 3, 'status' => 1, 'active_mac' => '00:11:22:33:44:01']
        );

        $status = TabStatus::updateOrCreate(
            ['tab_id' => $tab->id],
            ['current_balance' => 500.00, 'shift_total_bet' => 0.00, 'shift_total_win' => 0.00]
        );

        $payload = [
            'game_no'        => 'TEST-ROL-' . uniqid(),
            'winning_number' => 17,
            'tab_bets'       => [
                $tab->id => [
                    ['position_index' => 68, 'amount' => 50],   // Dot 68: Straight 17 -> Wins 50 + 1750 = 1800
                    ['position_index' => 150, 'amount' => 50],  // Dot 150: Red -> Loses 50
                ]
            ]
        ];

        $response = $this->postJson("/api/v1/station/{$tab->id}/roulette/result", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success'        => true,
            'winning_number' => 17,
            'winning_colour' => 'black',
            'settled_tabs'   => 1,
        ]);

        // Check TabStatus updated balance: 500 - 100 (total bet) + 1800 (win) = 2200
        $status->refresh();
        $this->assertEquals(2200.0, (float)$status->current_balance);

        // Check roulette_history record created
        $history = RouletteHistory::where('game_no', $payload['game_no'])->first();
        $this->assertNotNull($history);
        $this->assertEquals(17, $history->winning_number);
        $this->assertEquals('black', $history->winning_colour);
        $this->assertEquals(100.0, (float)$history->bet_amount);
        $this->assertEquals(1800.0, (float)$history->win_amount);
    }

    public function test_tab_bet_placement_validates_balance_and_lock()
    {
        $tab = Tab::firstOrCreate(
            ['tab_number' => 102],
            ['table_name' => 'TAB-102', 'game_type_id' => 3, 'status' => 1, 'active_mac' => '00:11:22:33:44:02']
        );

        // Set low balance
        TabStatus::updateOrCreate(
            ['tab_id' => $tab->id],
            ['current_balance' => 20.00]
        );

        // Attempt bet exceeding balance ($100 > $20)
        $failResponse = $this->postJson("/api/v1/tabs/{$tab->id}/bet", [
            'bets' => [
                ['position' => 'RED', 'amount' => 100]
            ]
        ]);
        $failResponse->assertStatus(422);
        $failResponse->assertJson(['success' => false, 'message' => 'Insufficient credit balance']);

        // Now fund tab
        TabStatus::where('tab_id', $tab->id)->update(['current_balance' => 500.00]);

        $successResponse = $this->postJson("/api/v1/tabs/{$tab->id}/bet", [
            'bets' => [
                ['position' => 'RED', 'amount' => 100]
            ]
        ]);
        $successResponse->assertStatus(200);
        $successResponse->assertJson(['success' => true]);
    }

    public function test_roulette_history_renders_table_and_modal_overlay()
    {
        $admin = $this->getAdmin();

        $tab = Tab::first();

        // Seed a known roulette history round
        $gameNo = 'MODAL-ROL-' . uniqid();
        $record = RouletteHistory::create([
            'table_id'       => $tab->id,
            'game_no'        => $gameNo,
            'tab_id'         => (string)$tab->table_name,
            'winning_number' => 17,
            'winning_colour' => 'black',
            'roulette_type'  => 'European',
            'bet_position'   => [
                ['position_index' => 68, 'position' => '68', 'label' => 'Straight Up 17', 'amount' => 50, 'is_win' => true, 'win_amount' => 1800],
                ['position_index' => 151, 'position' => 'BLACK', 'label' => 'Black', 'amount' => 100, 'is_win' => true, 'win_amount' => 200]
            ],
            'bet_numbers'    => [17],
            'bet_amount'     => 150.00,
            'win_amount'     => 2000.00,
            'current_credit' => 2850.00,
            'date_time'      => now(),
        ]);

        // 1. Check index page displays the round row and modal container (matching Baccarat / Andar Bahar UX)
        $response = $this->actingAs($admin)->get("/history?game=ROL&table_id={$tab->id}&game_no={$gameNo}");
        $response->assertStatus(200);
        $response->assertSee($gameNo);
        $response->assertSee('#17 BLACK');
        $response->assertSee('historyModal');
        $response->assertSee('modal-xl'); // Extra-large modal for board popup

        // 2. Check modal record detail API returns placed bets with position indices
        $apiResponse = $this->actingAs($admin)->getJson("/api/v1/history/roulette/{$record->id}");
        $apiResponse->assertStatus(200);
        $apiResponse->assertJson([
            'id'             => $record->id,
            'winning_number' => 17,
            'winning_colour' => 'black',
            'game_no'        => $gameNo,
        ]);
        $this->assertEquals(68, $apiResponse->json('bet_position.0.position_index'));
    }
}
