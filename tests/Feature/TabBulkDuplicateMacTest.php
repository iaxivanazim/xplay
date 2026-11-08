<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tab;
use App\Models\GameConfig;
use App\Models\GameType;
use App\Models\User;
use App\Models\Role;

class TabBulkDuplicateMacTest extends TestCase
{
    protected function getAdmin(): User
    {
        $user = User::first();
        if ($user) {
            return $user;
        }

        $adminRole = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Administrator']
        );

        return User::firstOrCreate(
            ['username' => 'testadmin_mac'],
            [
                'card_id'  => 'CRD-MAC-01',
                'name'     => 'Test Admin MAC',
                'password' => bcrypt('password'),
                'role_id'  => $adminRole->id,
            ]
        );
    }

    protected function getGameConfig(): GameConfig
    {
        $config = GameConfig::first();
        if ($config) {
            return $config;
        }

        $gameType = GameType::firstOrCreate(
            ['code' => 'ROL'],
            ['name' => 'Roulette', 'status' => 1]
        );

        return GameConfig::create([
            'game_type_id' => $gameType->id,
            'name'         => 'Test Config MAC',
            'min_bet'      => 1.00,
            'max_bet'      => 500.00,
            'is_active'    => 1,
            'is_default'   => 1,
        ]);
    }

    public function test_bulk_store_web_with_duplicate_mac_in_batch_redirects_with_proper_alert()
    {
        $admin = $this->getAdmin();
        $config = $this->getGameConfig();

        $startNum = (Tab::max('tab_number') ?? 100) + 10;
        $randHex = sprintf('%02X:%02X', rand(1, 250), rand(1, 250));
        $duplicateMac = "AA:BB:CC:DD:{$randHex}";

        // Submitting two tabs in the same batch with the identical MAC address
        $payload = [
            'game_config_id' => $config->id,
            'denomination'   => 0.01,
            'tabs' => [
                [
                    'tab_number' => $startNum,
                    'table_name' => "BatchTab-{$startNum}",
                    'ip_address' => '192.168.1.201',
                    'active_mac' => $duplicateMac,
                ],
                [
                    'tab_number' => $startNum + 1,
                    'table_name' => "BatchTab-" . ($startNum + 1),
                    'ip_address' => '192.168.1.202',
                    'active_mac' => strtolower(str_replace(':', '-', $duplicateMac)), // Case/dash difference
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('tabs.bulk_store'), $payload);

        // Must redirect back with input and errors, NEVER crash or show an unhandled error page
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['active_mac']);
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('Duplicate MAC address found within the submitted batch', $errorMessage);
        $this->assertStringContainsString($duplicateMac, $errorMessage);

        // Verify neither tab was persisted
        $this->assertDatabaseMissing('tabs', ['tab_number' => $startNum]);
        $this->assertDatabaseMissing('tabs', ['tab_number' => $startNum + 1]);
    }

    public function test_bulk_store_web_with_mac_already_in_database_redirects_with_proper_alert()
    {
        $admin = $this->getAdmin();
        $config = $this->getGameConfig();

        $randHex = sprintf('%02X:%02X', rand(1, 250), rand(1, 250));
        $existingMac = "AA:BB:DD:EE:{$randHex}";

        // Pre-create a tab with this MAC
        $existingTab = Tab::create([
            'tab_number'        => (Tab::max('tab_number') ?? 100) + 1,
            'table_name'        => 'VIP-Existing-' . uniqid(),
            'active_mac'        => $existingMac,
            'denomination'      => 0.01,
            'status'            => 1,
            'game_type_id'      => $config->game_type_id,
            'game_config_id'    => $config->id,
            'lock_status'       => 'unlocked',
            'connection_status' => 'online',
        ]);

        $newTabNum = (Tab::max('tab_number') ?? 100) + 5;

        // Try to bulk add a tab with the existing MAC
        $payload = [
            'game_config_id' => $config->id,
            'denomination'   => 0.01,
            'tabs' => [
                [
                    'tab_number' => $newTabNum,
                    'table_name' => "BatchTab-{$newTabNum}",
                    'ip_address' => '192.168.1.210',
                    'active_mac' => $existingMac,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('tabs.bulk_store'), $payload);

        // Must redirect back, NEVER throw unhandled 1062 Duplicate entry error page
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['active_mac']);
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('already exist in the database', $errorMessage);
        $this->assertStringContainsString($existingMac, $errorMessage);

        // Verify the new tab was not created
        $this->assertDatabaseMissing('tabs', ['tab_number' => $newTabNum]);
    }

    public function test_bulk_store_api_with_duplicate_mac_in_batch_returns_422_json()
    {
        $config = $this->getGameConfig();
        $startNum = (Tab::max('tab_number') ?? 200) + 20;
        $randHex = sprintf('%02X:%02X', rand(1, 250), rand(1, 250));
        $dupMac = "AA:CC:CC:11:{$randHex}";

        $payload = [
            'game_config_id' => $config->id,
            'denomination'   => 0.01,
            'tabs' => [
                [
                    'tab_number' => $startNum,
                    'table_name' => "ApiTab-{$startNum}",
                    'active_mac' => $dupMac,
                ],
                [
                    'tab_number' => $startNum + 1,
                    'table_name' => "ApiTab-" . ($startNum + 1),
                    'active_mac' => $dupMac,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/tabs/bulk', $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'DUPLICATE_MAC_IN_BATCH',
        ]);
        $response->assertJsonFragment([
            'conflicts' => [$dupMac],
        ]);
    }

    public function test_bulk_store_api_with_existing_database_mac_returns_409_json()
    {
        $config = $this->getGameConfig();
        $randHex = sprintf('%02X:%02X', rand(1, 250), rand(1, 250));
        $existingMac = "AA:CC:DD:88:{$randHex}";

        Tab::create([
            'tab_number'        => (Tab::max('tab_number') ?? 200) + 1,
            'table_name'        => 'VIP-Existing-API-' . uniqid(),
            'active_mac'        => $existingMac,
            'denomination'      => 0.01,
            'status'            => 1,
            'game_type_id'      => $config->game_type_id,
            'game_config_id'    => $config->id,
            'lock_status'       => 'unlocked',
            'connection_status' => 'online',
        ]);

        $startNum = (Tab::max('tab_number') ?? 300) + 30;

        $payload = [
            'game_config_id' => $config->id,
            'denomination'   => 0.01,
            'tabs' => [
                [
                    'tab_number' => $startNum,
                    'table_name' => "ApiConflictTab-{$startNum}",
                    'active_mac' => $existingMac,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/tabs/bulk', $payload);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'code'    => 'MAC_ALREADY_EXISTS',
        ]);
        $response->assertJsonFragment([
            'conflicts' => [$existingMac],
        ]);
    }

    public function test_bulk_store_succeeds_with_unique_and_null_mac_addresses()
    {
        $admin = $this->getAdmin();
        $config = $this->getGameConfig();
        $startNum = (Tab::max('tab_number') ?? 400) + 40;

        $randHex1 = sprintf('%02X:%02X', rand(1, 250), rand(1, 250));
        $randHex2 = sprintf('%02X:%02X', rand(1, 250), rand(1, 250));
        $mac1 = "12:34:56:78:{$randHex1}";
        $mac2 = "12:34:56:78:{$randHex2}";

        $payload = [
            'game_config_id' => $config->id,
            'denomination'   => 0.01,
            'tabs' => [
                [
                    'tab_number' => $startNum,
                    'table_name' => "SuccessTab-{$startNum}",
                    'ip_address' => '192.168.1.51',
                    'active_mac' => $mac1,
                ],
                [
                    'tab_number' => $startNum + 1,
                    'table_name' => "SuccessTab-" . ($startNum + 1),
                    'ip_address' => '192.168.1.52',
                    'active_mac' => $mac2,
                ],
                [
                    'tab_number' => $startNum + 2,
                    'table_name' => "SuccessTab-" . ($startNum + 2),
                    'ip_address' => '192.168.1.53',
                    'active_mac' => '', // Blank/null MAC address should be allowed multiple times
                ],
                [
                    'tab_number' => $startNum + 3,
                    'table_name' => "SuccessTab-" . ($startNum + 3),
                    'ip_address' => '192.168.1.54',
                    'active_mac' => null, // Null MAC address
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('tabs.bulk_store'), $payload);

        $response->assertStatus(302);
        $response->assertRedirect(route('tabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tabs', ['tab_number' => $startNum, 'active_mac' => $mac1]);
        $this->assertDatabaseHas('tabs', ['tab_number' => $startNum + 1, 'active_mac' => $mac2]);
        $this->assertDatabaseHas('tabs', ['tab_number' => $startNum + 2, 'active_mac' => null]);
        $this->assertDatabaseHas('tabs', ['tab_number' => $startNum + 3, 'active_mac' => null]);
    }
}
