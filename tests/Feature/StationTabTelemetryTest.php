<?php

namespace Tests\Feature;

use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\User;
use App\Models\GameType;
use Tests\TestCase;

class StationTabTelemetryTest extends TestCase
{
    protected User $user;
    protected Tab $tab1;
    protected Tab $tab2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::first() ?? User::factory()->create();
        $gameType = GameType::first() ?? GameType::create([
            'name'   => 'Roulette',
            'code'   => 'ROULETTE',
            'status' => 1,
        ]);
        $gameTypeId = $gameType->id;

        // Provision Tab 1 with IP and MAC
        $this->tab1 = Tab::firstOrCreate(
            ['tab_number' => 881],
            [
                'table_name'        => 'Telemetry Test Tab 1',
                'game_type_id'      => $gameTypeId,
                'denomination'      => 1.00,
                'status'            => 1,
                'lock_status'       => 'unlocked',
                'connection_status' => 'online',
                'ip_address'        => '192.168.1.81',
                'active_mac'        => '00:E0:4C:88:00:81',
            ]
        );
        $this->tab1->update([
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'ip_address'        => '192.168.1.81',
            'active_mac'        => '00:E0:4C:88:00:81',
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);
        TabStatus::firstOrCreate(['tab_id' => $this->tab1->id])->update([
            'connection_status' => 'online',
            'current_balance'   => 50.00,
        ]);

        // Provision Tab 2 with IP and MAC
        $this->tab2 = Tab::firstOrCreate(
            ['tab_number' => 882],
            [
                'table_name'        => 'Telemetry Test Tab 2',
                'game_type_id'      => $gameTypeId,
                'denomination'      => 1.00,
                'status'            => 1,
                'lock_status'       => 'unlocked',
                'connection_status' => 'online',
                'ip_address'        => '192.168.1.82',
                'active_mac'        => '00:E0:4C:88:00:82',
            ]
        );
        $this->tab2->update([
            'status'            => 1,
            'lock_status'       => 'unlocked',
            'ip_address'        => '192.168.1.82',
            'active_mac'        => '00:E0:4C:88:00:82',
            'connection_status' => 'online',
            'last_seen_at'      => now(),
        ]);
        TabStatus::firstOrCreate(['tab_id' => $this->tab2->id])->update([
            'connection_status' => 'online',
            'current_balance'   => 0.00,
        ]);
    }

    public function test_single_tab_heartbeat_with_telemetry_keeps_tab_online_and_persists_battery(): void
    {
        $response = $this->postJson("/api/v1/tabs/{$this->tab1->id}/heartbeat", [
            'tab_connected' => true,
            'battery'       => 94,
            'is_charging'   => true,
            'wifi_rssi'     => -49,
            'app_version'   => '1.5.0',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'tab_id'            => $this->tab1->id,
                'connection_status' => 'online',
                'is_online'         => true,
                'telemetry'         => [
                    'tab_connected' => true,
                    'battery'       => 94,
                    'is_charging'   => true,
                    'wifi_rssi'     => -49,
                    'app_version'   => '1.5.0',
                ],
            ]);

        $this->assertTrue($this->tab1->fresh()->isOnline());
        $status = TabStatus::where('tab_id', $this->tab1->id)->first();
        $this->assertEquals(94, $status->recovery_data['telemetry']['battery']);
        $this->assertTrue($status->recovery_data['telemetry']['is_charging']);
    }

    public function test_single_tab_heartbeat_reporting_tab_disconnected_immediately_sets_tab_offline(): void
    {
        $response = $this->postJson("/api/v1/tabs/{$this->tab1->id}/heartbeat", [
            'tab_connected'     => false,
            'disconnect_reason' => 'wifi_drop',
            'battery'           => 12,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'tab_id'            => $this->tab1->id,
                'connection_status' => 'offline',
                'is_online'         => false,
                'telemetry'         => [
                    'tab_connected'     => false,
                    'disconnect_reason' => 'wifi_drop',
                    'battery'           => 12,
                ],
            ]);

        $this->assertFalse($this->tab1->fresh()->isOnline());
        $this->assertEquals('offline', $this->tab1->fresh()->connection_status);

        $status = TabStatus::where('tab_id', $this->tab1->id)->first();
        $this->assertEquals('wifi_drop', $status->recovery_data['telemetry']['disconnect_reason']);
    }

    public function test_batch_station_telemetry_syncs_multiple_tabs_atomically(): void
    {
        $payload = [
            'station_id' => 101,
            'tabs'       => [
                [
                    'tab_id'       => $this->tab1->id,
                    'is_connected' => true,
                    'battery'      => 85,
                    'is_charging'  => false,
                    'wifi_rssi'    => -55,
                    'app_version'  => '1.5.0',
                ],
                [
                    'tab_id'            => $this->tab2->id,
                    'is_connected'      => false,
                    'disconnect_reason' => 'ping_timeout',
                    'battery'           => 40,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/station/101/telemetry', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success'         => true,
                'station_id'      => '101',
                'total_processed' => 2,
                'online_count'    => 1,
                'offline_count'   => 1,
            ]);

        $this->assertTrue($this->tab1->fresh()->isOnline());
        $this->assertEquals('online', $this->tab1->fresh()->connection_status);

        $this->assertFalse($this->tab2->fresh()->isOnline());
        $this->assertEquals('offline', $this->tab2->fresh()->connection_status);

        $status2 = TabStatus::where('tab_id', $this->tab2->id)->first();
        $this->assertEquals('ping_timeout', $status2->recovery_data['telemetry']['disconnect_reason']);
    }

    public function test_batch_station_telemetry_supports_lookup_by_tab_number_and_mac(): void
    {
        $payload = [
            'tabs' => [
                [
                    'tab_number'   => 881,
                    'is_connected' => true,
                    'battery'      => 99,
                ],
                [
                    'mac'          => '00:E0:4C:88:00:82',
                    'is_connected' => true,
                    'battery'      => 77,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/station/tabs/telemetry', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success'         => true,
                'total_processed' => 2,
                'online_count'    => 2,
                'offline_count'   => 0,
            ]);

        $this->assertTrue($this->tab1->fresh()->isOnline());
        $this->assertTrue($this->tab2->fresh()->isOnline());
    }

    public function test_batch_station_telemetry_validation_requires_tabs_array(): void
    {
        $response = $this->postJson('/api/v1/station/telemetry', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation error',
            ]);
    }
}
