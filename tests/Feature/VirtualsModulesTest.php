<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\GameConfig;
use App\Models\TableLedger;

class VirtualsModulesTest extends TestCase
{
    protected function getAdmin()
    {
        return User::first() ?? User::factory()->create();
    }

    public function test_dashboard_renders_successfully()
    {
        $admin = $this->getAdmin();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('STA');
        $response->assertSee('Megapot');
        $response->assertSee('Amatic-1');
    }

    public function test_tabs_index_renders_with_mockup_columns()
    {
        $admin = $this->getAdmin();

        $response = $this->actingAs($admin)->get('/tabs');

        $response->assertStatus(200);
        $response->assertSee('DEN...');
        $response->assertSee('CREDITS');
        $response->assertSee('LAST BET');
        $response->assertSee('GAMES');
        $response->assertSee('BILL IN');
        $response->assertSee('FT IN');
        $response->assertSee('FT OUT');
    }

    public function test_game_configs_index_renders_successfully()
    {
        $admin = $this->getAdmin();

        $response = $this->actingAs($admin)->get('/game_configs');

        $response->assertStatus(200);
        $response->assertSee('Game Configurations');
        $response->assertSee('Standard European Roulette');
    }

    public function test_cashier_can_cashin_and_generate_otp()
    {
        $admin = $this->getAdmin();
        $tab = Tab::first();

        $response = $this->actingAs($admin)->post("/dashboard/tab/{$tab->id}/cashin", [
            'amount'         => 150.00,
            'player_name'    => 'Test High Roller',
            'player_phone'   => '+1 555-9988',
            'player_card_id' => 'CARD-TEST-01',
        ]);

        $response->assertRedirect('/dashboard');

        $status = TabStatus::where('tab_id', $tab->id)->first();
        $this->assertNotNull($status);
        $this->assertNotNull($status->current_otp);
        $this->assertGreaterThanOrEqual(150.00, (float)$status->current_balance);
        $this->assertEquals('Test High Roller', $status->player_name);

        // Verify direct cage ledger entry
        $ledger = TableLedger::where('tab_id', $tab->id)->where('txn_type', 'BUYIN')->latest('txn_id')->first();
        $this->assertNotNull($ledger);
        $this->assertEquals(150.00, (float)$ledger->amount);
    }

    public function test_cashier_can_cashout_balance()
    {
        $admin = $this->getAdmin();
        $tab = Tab::first();

        // First cashin so there is balance
        $this->actingAs($admin)->post("/dashboard/tab/{$tab->id}/cashin", [
            'amount' => 200.00,
        ]);

        $response = $this->actingAs($admin)->post("/dashboard/tab/{$tab->id}/cashout");

        $response->assertRedirect('/dashboard');

        $status = TabStatus::where('tab_id', $tab->id)->first();
        $this->assertEquals(0.00, (float)$status->current_balance);
        $this->assertEquals('idle', $status->status);

        // Verify direct cage ledger entry
        $ledger = TableLedger::where('tab_id', $tab->id)->where('txn_type', 'CASHOUT')->latest('txn_id')->first();
        $this->assertNotNull($ledger);
    }

    public function test_tab_api_configuration_endpoint()
    {
        $tab = Tab::first();

        $response = $this->getJson("/api/v1/tabs/{$tab->id}/configuration");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'tab_id',
            'tab_number',
            'tab_name',
            'denomination',
            'assigned_games',
        ]);
    }

    public function test_failure_restoration_api_endpoint()
    {
        $tab = Tab::first();

        // Set a balance and OTP in status
        $status = TabStatus::where('tab_id', $tab->id)->first();
        $status->update([
            'current_balance' => 350.00,
            'current_otp'     => '883921',
            'player_name'     => 'Disaster Recovery Player',
            'recovery_data'   => [
                'session_state' => 'restored_test',
            ],
        ]);

        $response = $this->postJson("/api/v1/tabs/{$tab->id}/restore");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'restored_state' => [
                'tab_id'          => $tab->id,
                'current_balance' => 350.00,
                'current_otp'     => '883921',
                'player_name'     => 'Disaster Recovery Player',
            ],
        ]);
    }

    public function test_tab_register_mac_accepts_mac_payload_and_never_redirects()
    {
        $tab = Tab::first();

        // 1. Sending 'mac' key without explicit Accept header must return 200 JSON, not redirect
        $response = $this->post("/api/v1/tabs/{$tab->id}/register-mac", [
            'mac'        => '00:E0:4C:68:00:01',
            'ip_address' => '192.168.1.101',
            'override'   => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'tab_id'     => $tab->id,
                'active_mac' => '00:E0:4C:68:00:01',
            ],
        ]);

        // 2. Validation failure without Accept header must return 422 JSON, never 302 to login
        $failResponse = $this->post("/api/v1/tabs/{$tab->id}/register-mac", [
            'mac' => 'INVALID_FORMAT',
        ]);

        $failResponse->assertStatus(422);
        $failResponse->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
    }
}
