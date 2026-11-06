<?php

namespace Tests\Feature;

use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabSession;
use App\Models\User;
use App\Services\RouletteEngine;
use Tests\TestCase;

class TabHeartbeatAndBalanceTest extends TestCase
{
    protected User $adminUser;
    protected Tab $testTab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::first() ?? User::factory()->create();
        $this->testTab = Tab::first() ?? Tab::create([
            'tab_number'        => 'TAB-TEST-01',
            'table_name'        => 'VIP Test Tab',
            'denomination'      => 1.00,
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'connection_status' => 'online',
        ]);

        // Ensure status record exists with 0 balance
        $status = TabStatus::firstOrCreate(['tab_id' => $this->testTab->id]);
        $status->update([
            'current_balance'      => 0.00,
            'balance_version'      => 1,
            'last_op_type'         => null,
            'last_op_amount'       => 0.00,
            'last_op_prev_balance' => 0.00,
            'last_op_new_balance'  => 0.00,
            'last_op_ref'          => null,
            'last_op_notes'        => null,
            'last_op_at'           => null,
        ]);
    }

    public function test_heartbeat_returns_initial_balance_and_null_operation(): void
    {
        $response = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'tab_id'            => $this->testTab->id,
                'current_balance'   => 0.00,
                'balance_version'   => 1,
                'lock_status'       => 'unlocked',
                'connection_status' => 'online',
                'last_operation'    => null,
            ]);
    }

    public function test_cage_cashin_buyin_updates_balance_and_is_delivered_via_heartbeat(): void
    {
        // 1. Cashier performs Cashin (Buyin) at the cage
        $response = $this->actingAs($this->adminUser)
            ->post("/dashboard/tab/{$this->testTab->id}/cashin", [
                'amount'      => 500.00,
                'player_name' => 'High Roller Test',
            ]);

        $response->assertSessionHasNoErrors();

        // 2. Tab / Middleware sends periodic heartbeat
        $hbResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");

        $hbResponse->assertStatus(200)
            ->assertJson([
                'success'         => true,
                'tab_id'          => $this->testTab->id,
                'current_balance' => 500.00,
                'last_operation'  => [
                    'type'             => 'BUYIN',
                    'amount'           => 500.00,
                    'previous_balance' => 0.00,
                    'new_balance'      => 500.00,
                    'notes'            => 'Cage Buyin',
                ],
            ]);

        $this->assertGreaterThan(1, $hbResponse->json('balance_version'));
    }

    public function test_heartbeat_detects_balance_changed_flag_correctly(): void
    {
        // Setup a known version and balance
        $status = $this->testTab->statusRecord;
        $status->update([
            'current_balance' => 200.00,
            'balance_version' => 5,
        ]);

        // Client sends matching known_version -> balance_changed is false
        $matchResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat", [
            'known_version' => 5,
        ]);
        $matchResponse->assertJson(['balance_changed' => false]);

        // Client sends outdated known_version -> balance_changed is true
        $staleResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat", [
            'known_version' => 4,
        ]);
        $staleResponse->assertJson(['balance_changed' => true]);

        // Client sends outdated known_balance -> balance_changed is true
        $staleBalResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat", [
            'known_balance' => 150.00,
        ]);
        $staleBalResponse->assertJson(['balance_changed' => true]);
    }

    public function test_cage_cashout_resets_balance_and_is_delivered_via_heartbeat(): void
    {
        // Set balance to 300
        $this->testTab->statusRecord->update([
            'current_balance' => 300.00,
        ]);

        // 1. Cashier performs Cashout
        $response = $this->actingAs($this->adminUser)
            ->post("/dashboard/tab/{$this->testTab->id}/cashout");

        $response->assertSessionHasNoErrors();

        // 2. Next heartbeat reflects CASHOUT
        $hbResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");

        $hbResponse->assertStatus(200)
            ->assertJson([
                'success'         => true,
                'tab_id'          => $this->testTab->id,
                'current_balance' => 0.00,
                'last_operation'  => [
                    'type'             => 'CASHOUT',
                    'amount'           => 300.00,
                    'previous_balance' => 300.00,
                    'new_balance'      => 0.00,
                    'notes'            => 'Cage Cashout',
                ],
            ]);
    }

    public function test_api_win_endpoint_updates_balance_and_is_reflected_in_heartbeat(): void
    {
        $this->testTab->statusRecord->update(['current_balance' => 100.00, 'balance_version' => 2]);

        // 1. External system calls win API
        $winResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/win", [
            'amount'    => 75.50,
            'game_no'   => 'BAC-ROUND-1002',
            'game_type' => 'BAC',
            'notes'     => 'Player win with natural 9',
        ]);

        $winResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'current_balance'  => 175.50,
                    'previous_balance' => 100.00,
                    'operation'        => [
                        'type'      => 'WIN',
                        'amount'    => 75.50,
                        'reference' => 'BAC-ROUND-1002',
                    ],
                ],
            ]);

        // 2. Heartbeat receives updated WIN operation
        $hbResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");

        $hbResponse->assertStatus(200)
            ->assertJson([
                'current_balance' => 175.50,
                'last_operation'  => [
                    'type'             => 'WIN',
                    'amount'           => 75.50,
                    'previous_balance' => 100.00,
                    'new_balance'      => 175.50,
                    'reference'        => 'BAC-ROUND-1002',
                ],
            ]);
    }

    public function test_api_loss_endpoint_updates_balance_and_is_reflected_in_heartbeat(): void
    {
        $this->testTab->statusRecord->update(['current_balance' => 200.00, 'balance_version' => 3]);

        // 1. External system calls loss API
        $lossResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/loss", [
            'amount'    => 50.00,
            'game_no'   => 'AB-ROUND-8821',
            'game_type' => 'AB',
            'notes'     => 'Bahar bet lost',
        ]);

        $lossResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'current_balance'  => 150.00,
                    'previous_balance' => 200.00,
                    'operation'        => [
                        'type'      => 'LOSS',
                        'amount'    => 50.00,
                        'reference' => 'AB-ROUND-8821',
                    ],
                ],
            ]);

        // 2. Heartbeat reflects LOSS
        $hbResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");

        $hbResponse->assertStatus(200)
            ->assertJson([
                'current_balance' => 150.00,
                'last_operation'  => [
                    'type'             => 'LOSS',
                    'amount'           => 50.00,
                    'previous_balance' => 200.00,
                    'new_balance'      => 150.00,
                    'reference'        => 'AB-ROUND-8821',
                ],
            ]);
    }

    public function test_api_game_result_endpoint_updates_net_balance(): void
    {
        $this->testTab->statusRecord->update(['current_balance' => 100.00, 'balance_version' => 1]);

        // Win result: Bet 50, Win 150 -> Net +100
        $res = $this->postJson("/api/v1/tabs/{$this->testTab->id}/game-result", [
            'bet_amount' => 50.00,
            'win_amount' => 150.00,
            'game_no'    => 'RND-GAME-77',
            'game_type'  => 'ROU',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'current_balance' => 200.00,
                    'operation'       => [
                        'type'   => 'WIN',
                        'amount' => 150.00,
                    ],
                ],
            ]);

        $hb = $this->getJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");
        $hb->assertJson([
            'current_balance' => 200.00,
            'last_operation'  => [
                'type' => 'WIN',
            ],
        ]);
    }

    public function test_roulette_engine_settlement_updates_tab_balance_and_is_reflected_in_heartbeat(): void
    {
        $this->testTab->statusRecord->update(['current_balance' => 200.00, 'balance_version' => 1]);

        $engine = app(RouletteEngine::class);

        // Place a bet on Straight Up 17 ($50) for this tab
        $tabBets = [
            $this->testTab->tab_number => [
                ['position' => '17', 'amount' => 50, 'label' => 'Straight 17']
            ]
        ];

        // Winning number is 17 (35:1 payout = $1750 win + $50 return = $1800 total payout)
        $engine->settleRound(
            tableId: 1,
            gameNo: 'ROU-WHEEL-99',
            winningNumber: 17,
            tabBets: $tabBets
        );

        // Tab should have: 200 - 50 + 1800 = 1950.00
        $hbResponse = $this->postJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");

        $hbResponse->assertStatus(200)
            ->assertJson([
                'current_balance' => 1950.00,
                'last_operation'  => [
                    'type'      => 'WIN',
                    'amount'    => 1800.00,
                    'reference' => 'ROU-WHEEL-99',
                ],
            ]);
    }

    public function test_balance_logs_endpoint_returns_audit_history(): void
    {
        $this->testTab->statusRecord->update(['current_balance' => 100.00]);

        $this->postJson("/api/v1/tabs/{$this->testTab->id}/win", [
            'amount'  => 40.00,
            'game_no' => 'AUDIT-01',
        ]);

        $this->postJson("/api/v1/tabs/{$this->testTab->id}/loss", [
            'amount'  => 20.00,
            'game_no' => 'AUDIT-02',
        ]);

        $logsResponse = $this->getJson("/api/v1/tabs/{$this->testTab->id}/balance-logs");

        $logsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tab_id'  => $this->testTab->id,
            ])
            ->assertJsonStructure([
                'success',
                'tab_id',
                'tab_number',
                'current_balance',
                'balance_version',
                'count',
                'data' => [
                    '*' => [
                        'id',
                        'balance_version',
                        'op_type',
                        'amount',
                        'prev_balance',
                        'new_balance',
                        'reference',
                    ]
                ]
            ]);

        $this->assertGreaterThanOrEqual(2, $logsResponse->json('count'));
    }

    public function test_get_and_non_v1_heartbeat_aliases_work(): void
    {
        // 1. GET /api/v1/tabs/{id}/heartbeat
        $getV1 = $this->getJson("/api/v1/tabs/{$this->testTab->id}/heartbeat");
        $getV1->assertStatus(200)->assertJson(['success' => true]);

        // 2. POST /api/tabs/{id}/heartbeat (non-v1 alias)
        $postNonV1 = $this->postJson("/api/tabs/{$this->testTab->id}/heartbeat");
        $postNonV1->assertStatus(200)->assertJson(['success' => true]);

        // 3. GET /api/tabs/{id}/heartbeat (non-v1 alias)
        $getNonV1 = $this->getJson("/api/tabs/{$this->testTab->id}/heartbeat");
        $getNonV1->assertStatus(200)->assertJson(['success' => true]);
    }
}
