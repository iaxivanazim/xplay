<?php

namespace Tests\Feature;

use App\Models\GameConfig;
use App\Models\GameType;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\User;
use Tests\TestCase;

class TabStatusEAndATest extends TestCase
{
    protected User $adminUser;
    protected GameConfig $testConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::first() ?? User::factory()->create();

        $gameType = GameType::first() ?? GameType::create([
            'name'   => 'Roulette',
            'code'   => 'ROL',
            'status' => 1,
        ]);

        $this->testConfig = GameConfig::first() ?? GameConfig::create([
            'name'         => 'Default Config',
            'game_type_id' => $gameType->id,
            'min_bet'      => '10',
            'max_bet'      => '500',
            'is_active'    => 1,
        ]);
    }

    public function test_e_changes_state_when_tab_is_edited_unchecked_and_checked(): void
    {
        $tab = Tab::create([
            'tab_number'        => 9901,
            'table_name'        => 'Test-E-Tab-01',
            'game_type_id'      => $this->testConfig->game_type_id,
            'game_config_id'    => $this->testConfig->id,
            'denomination'      => 0.0100,
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'connection_status' => 'offline',
        ]);
        TabStatus::create([
            'tab_id'            => $tab->id,
            'status'            => 'idle',
            'lock_status'       => 'unlocked',
            'current_balance'   => 0.00,
            'connection_status' => 'offline',
        ]);

        // 1. Initial state: status is 1 (Enabled)
        $this->assertEquals(1, $tab->status);

        // 2. Edit tab: Uncheck "Tab Enabled" (send status = 0 / omitted)
        $response = $this->actingAs($this->adminUser)->put("/tabs/{$tab->id}", [
            'tab_number'     => 9901,
            'table_name'     => 'Test-E-Tab-01',
            'game_config_id' => $this->testConfig->id,
            'denomination'   => 0.0100,
            'lock_status'    => 'unlocked',
            'status'         => 0, // Unchecked
        ]);

        $response->assertRedirect('/tabs');
        $tab->refresh();
        $this->assertFalse((bool) $tab->status, 'Tab status should be 0 (disabled) after unchecking status');
        $this->assertEquals('disabled', $tab->statusRecord->status);

        // Verify index view renders 'E' as 'N'
        $indexResponse = $this->actingAs($this->adminUser)->get('/tabs?search=Test-E-Tab-01');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('badge-n', false);

        // 3. Edit tab: Check "Tab Enabled" back to 1
        $response2 = $this->actingAs($this->adminUser)->put("/tabs/{$tab->id}", [
            'tab_number'     => 9901,
            'table_name'     => 'Test-E-Tab-01',
            'game_config_id' => $this->testConfig->id,
            'denomination'   => 0.0100,
            'lock_status'    => 'unlocked',
            'status'         => 1, // Checked
        ]);

        $response2->assertRedirect('/tabs');
        $tab->refresh();
        $this->assertTrue((bool) $tab->status, 'Tab status should be 1 (enabled) after checking status');
        $this->assertEquals('idle', $tab->statusRecord->status);

        // Clean up
        $tab->statusRecord()->delete();
        $tab->delete();
    }

    public function test_a_is_n_when_ip_or_mac_is_not_registered(): void
    {
        // 1. Neither IP nor MAC registered
        $tab = Tab::create([
            'tab_number'        => 9902,
            'table_name'        => 'Test-A-NoHardware',
            'game_type_id'      => $this->testConfig->game_type_id,
            'game_config_id'    => $this->testConfig->id,
            'ip_address'        => null,
            'active_mac'        => null,
            'denomination'      => 0.0100,
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'connection_status' => 'online', // Even if legacy marked 'online'
            'last_seen_at'      => now(),
        ]);

        $this->assertFalse($tab->isOnline(), 'Tab without IP and MAC cannot be online');

        // 2. Only IP registered, no MAC
        $tab->update([
            'ip_address' => '192.168.1.200',
            'active_mac' => null,
        ]);
        $this->assertFalse($tab->isOnline(), 'Tab without MAC cannot be online');

        // 3. Only MAC registered, no IP
        $tab->update([
            'ip_address' => null,
            'active_mac' => 'AA:BB:CC:11:22:33',
        ]);
        $this->assertFalse($tab->isOnline(), 'Tab without IP cannot be online');

        $tab->delete();
    }

    public function test_a_is_n_when_heartbeat_is_missing_or_stale(): void
    {
        $tab = Tab::create([
            'tab_number'        => 9903,
            'table_name'        => 'Test-A-Heartbeat',
            'game_type_id'      => $this->testConfig->game_type_id,
            'game_config_id'    => $this->testConfig->id,
            'ip_address'        => '192.168.1.201',
            'active_mac'        => 'AA:BB:CC:11:22:44',
            'denomination'      => 0.0100,
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'connection_status' => 'online',
            'last_seen_at'      => null, // Never received heartbeat
        ]);

        // 1. Null heartbeat
        $this->assertFalse($tab->isOnline(), 'Tab with null heartbeat cannot be online');

        // 2. Stale heartbeat (older than 90s timeout)
        $tab->update(['last_seen_at' => now()->subMinutes(10)]);
        $this->assertFalse($tab->isOnline(), 'Tab with stale heartbeat (>90s) must be offline');

        // Sync stale connections
        Tab::syncStaleConnections();
        $tab->refresh();
        $this->assertEquals('offline', $tab->connection_status, 'syncStaleConnections must mark stale tabs offline');

        // 3. Active recent heartbeat (constant heartbeat)
        $tab->update([
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);
        $this->assertTrue($tab->isOnline(), 'Tab with IP, MAC, and recent heartbeat must be online');

        $tab->delete();
    }

    public function test_unregister_mac_immediately_sets_tab_to_offline(): void
    {
        $tab = Tab::create([
            'tab_number'        => 9904,
            'table_name'        => 'Test-A-Unregister',
            'game_type_id'      => $this->testConfig->game_type_id,
            'game_config_id'    => $this->testConfig->id,
            'ip_address'        => '192.168.1.202',
            'active_mac'        => 'AA:BB:CC:11:22:55',
            'denomination'      => 0.0100,
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);
        $status = TabStatus::create([
            'tab_id'            => $tab->id,
            'status'            => 'idle',
            'lock_status'       => 'unlocked',
            'current_balance'   => 0.00,
            'connection_status' => 'online',
            'active_mac'        => 'AA:BB:CC:11:22:55',
        ]);

        $this->assertTrue($tab->isOnline());

        // Unregister MAC
        $response = $this->actingAs($this->adminUser)->post("/tabs/{$tab->id}/unregister-mac");
        $response->assertRedirect();

        $tab->refresh();
        $status->refresh();

        $this->assertNull($tab->active_mac);
        $this->assertEquals('offline', $tab->connection_status);
        $this->assertNull($status->active_mac);
        $this->assertEquals('offline', $status->connection_status);
        $this->assertFalse($tab->isOnline());

        $status->delete();
        $tab->delete();
    }
}
