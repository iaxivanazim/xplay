<?php

namespace Tests\Feature;

use App\Models\GameConfig;
use App\Models\GameType;
use App\Models\User;
use Tests\TestCase;

class GameConfigDeviceIpTest extends TestCase
{
    protected User $adminUser;
    protected GameConfig $testConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::first() ?? User::factory()->create();
        $this->testConfig = GameConfig::first() ?? GameConfig::create([
            'name'         => 'Test Roulette Config',
            'game_type_id' => GameType::first()->id ?? 1,
            'min_bet'      => '10|20',
            'max_bet'      => '100|200',
            'is_active'    => 1,
        ]);
    }

    public function test_create_view_contains_device_ip_field(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('game_configs.create'));

        $response->assertStatus(200);
        $response->assertSee('Device IP Address');
        $response->assertSee('name="device_ip"', false);
    }

    public function test_edit_view_contains_device_ip_field(): void
    {
        $this->testConfig->update(['device_ip' => '192.168.1.42']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('game_configs.edit', $this->testConfig->id));

        $response->assertStatus(200);
        $response->assertSee('Device IP Address');
        $response->assertSee('192.168.1.42');
        $response->assertSee('name="device_ip"', false);
    }

    public function test_store_game_config_saves_device_ip(): void
    {
        $gameType = GameType::first();

        $response = $this->actingAs($this->adminUser)
            ->post(route('game_configs.store'), [
                'name'         => 'New Config With IP ' . uniqid(),
                'game_type_id' => $gameType->id,
                'min_bet'      => '10|25',
                'max_bet'      => '100|250',
                'device_ip'    => '10.20.30.40',
                'is_active'    => 1,
            ]);

        $response->assertRedirect(route('game_configs.index'));
        $this->assertDatabaseHas('game_configs', [
            'device_ip' => '10.20.30.40',
        ]);
    }

    public function test_update_game_config_updates_device_ip(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->put(route('game_configs.update', $this->testConfig->id), [
                'name'         => $this->testConfig->name,
                'game_type_id' => $this->testConfig->game_type_id,
                'min_bet'      => $this->testConfig->min_bet,
                'max_bet'      => $this->testConfig->max_bet,
                'device_ip'    => '172.16.0.99',
                'is_active'    => 1,
            ]);

        $response->assertRedirect(route('game_configs.index'));
        $this->assertEquals('172.16.0.99', $this->testConfig->fresh()->device_ip);
    }

    public function test_api_can_list_game_configs(): void
    {
        $response = $this->getJson('/api/v1/game-configs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'count',
                'data' => [
                    '*' => ['id', 'name', 'game_type', 'device_ip']
                ]
            ]);
    }

    public function test_api_can_insert_device_ip_via_url_id(): void
    {
        $response = $this->postJson("/api/v1/game-configs/{$this->testConfig->id}/device-ip", [
            'device_ip' => '192.168.10.88',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'        => $this->testConfig->id,
                    'device_ip' => '192.168.10.88',
                ]
            ]);

        $this->assertEquals('192.168.10.88', $this->testConfig->fresh()->device_ip);
    }

    public function test_api_can_insert_device_ip_using_ip_alias_key(): void
    {
        $response = $this->postJson("/api/v1/game-configs/{$this->testConfig->id}/device-ip", [
            'ip' => '10.0.5.15',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'device_ip' => '10.0.5.15',
                ]
            ]);

        $this->assertEquals('10.0.5.15', $this->testConfig->fresh()->device_ip);
    }

    public function test_api_can_insert_device_ip_via_payload_config_id(): void
    {
        $response = $this->postJson('/api/v1/game-configs/device-ip', [
            'config_id' => $this->testConfig->id,
            'device_ip' => '192.168.1.200',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'        => $this->testConfig->id,
                    'device_ip' => '192.168.1.200',
                ]
            ]);

        $this->assertEquals('192.168.1.200', $this->testConfig->fresh()->device_ip);
    }

    public function test_api_auto_captures_client_ip_when_not_provided(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.77'])
            ->postJson("/api/v1/game-configs/{$this->testConfig->id}/device-ip");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'device_ip' => '192.168.1.77',
                ]
            ]);

        $this->assertEquals('192.168.1.77', $this->testConfig->fresh()->device_ip);
    }

    public function test_api_rejects_invalid_ip_format(): void
    {
        $response = $this->postJson("/api/v1/game-configs/{$this->testConfig->id}/device-ip", [
            'device_ip' => 'not-a-valid-ip-address',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code'    => 'INVALID_IP_ADDRESS',
            ]);
    }

    public function test_api_returns_404_for_unknown_config(): void
    {
        $response = $this->postJson('/api/v1/game-configs/999999/device-ip', [
            'device_ip' => '192.168.1.1',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'CONFIG_NOT_FOUND',
            ]);
    }

    public function test_api_can_get_current_device_ip(): void
    {
        $this->testConfig->update(['device_ip' => '192.168.100.55']);

        $response = $this->getJson("/api/v1/game-configs/{$this->testConfig->id}/device-ip");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'        => $this->testConfig->id,
                    'device_ip' => '192.168.100.55',
                ]
            ]);
    }

    public function test_api_can_clear_device_ip(): void
    {
        $this->testConfig->update(['device_ip' => '192.168.1.1']);

        $response = $this->postJson("/api/v1/game-configs/{$this->testConfig->id}/device-ip", [
            'clear' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'device_ip' => null,
                ]
            ]);

        $this->assertNull($this->testConfig->fresh()->device_ip);
    }

    public function test_non_v1_alias_works(): void
    {
        $response = $this->postJson("/api/game-configs/{$this->testConfig->id}/device-ip", [
            'device_ip' => '10.10.10.10',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'device_ip' => '10.10.10.10',
                ]
            ]);

        $this->assertEquals('10.10.10.10', $this->testConfig->fresh()->device_ip);
    }
}
