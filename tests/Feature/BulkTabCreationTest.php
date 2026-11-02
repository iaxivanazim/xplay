<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tab;
use App\Models\TabStatus;
use App\Models\TabGameConfig;
use App\Models\GameConfig;

class BulkTabCreationTest extends TestCase
{
    protected function getAdmin()
    {
        return User::first() ?? User::factory()->create();
    }

    public function test_bulk_create_screen_can_be_rendered()
    {
        $admin = $this->getAdmin();

        $response = $this->actingAs($admin)->get('/tabs/bulk-create');

        $response->assertStatus(200);
        $response->assertSee('Bulk Add Tabs');
        $response->assertSee('Basic Game Configuration');
        $response->assertSee('Sequence Generator');
        $response->assertSee('Batch Preview');
    }

    public function test_bulk_store_creates_tabs_via_pattern_generator()
    {
        $admin = $this->getAdmin();
        $config = GameConfig::where('is_active', 1)->first() ?? GameConfig::first();

        $startNum = 200;
        $quantity = 5;

        // Clean up if already exists
        Tab::whereBetween('tab_number', [$startNum, $startNum + $quantity - 1])->delete();

        $response = $this->actingAs($admin)->post('/tabs/bulk-store', [
            'game_config_id' => $config->id,
            'denomination'   => 0.0100,
            'lock_status'    => 'unlocked',
            'naming_prefix'  => 'BulkAmatic-',
            'start_number'   => $startNum,
            'quantity'       => $quantity,
            'start_ip'       => '192.168.10.1',
        ]);

        $response->assertRedirect('/tabs');
        $response->assertSessionHas('success');

        for ($i = 0; $i < $quantity; $i++) {
            $num = $startNum + $i;
            $expectedName = "BulkAmatic-{$num}";
            $expectedIp   = "192.168.10." . (1 + $i);

            $tab = Tab::where('tab_number', $num)->first();
            $this->assertNotNull($tab, "Tab #{$num} was not created");
            $this->assertEquals($expectedName, $tab->table_name);
            $this->assertEquals($expectedIp, $tab->ip_address);
            $this->assertEquals($config->id, $tab->game_config_id);
            $this->assertEquals(0.0100, (float)$tab->denomination);
            $this->assertEquals('unlocked', $tab->lock_status);

            // Verify TabGameConfig pivot
            $this->assertDatabaseHas('tab_game_configs', [
                'tab_id'         => $tab->id,
                'game_config_id' => $config->id,
                'is_active'      => 1,
            ]);

            // Verify TabStatus record
            $status = TabStatus::where('tab_id', $tab->id)->first();
            $this->assertNotNull($status, "TabStatus for tab #{$num} not found");
            $this->assertEquals(0.00, (float)$status->current_balance);
            $this->assertEquals('unlocked', $status->lock_status);
        }
    }

    public function test_bulk_store_creates_tabs_via_explicit_tabs_array()
    {
        $admin = $this->getAdmin();
        $config = GameConfig::where('is_active', 1)->first() ?? GameConfig::first();

        // Custom tabs
        $customTabs = [
            [
                'tab_number' => 301,
                'table_name' => 'CustomApex-A',
                'ip_address' => '10.0.0.11',
                'active_mac' => '00:11:22:33:44:A1',
            ],
            [
                'tab_number' => 302,
                'table_name' => 'CustomApex-B',
                'ip_address' => '10.0.0.12',
                'active_mac' => '00:11:22:33:44:A2',
            ],
            [
                'tab_number' => 303,
                'table_name' => 'CustomApex-C',
                'ip_address' => null,
                'active_mac' => null,
            ],
        ];

        Tab::whereIn('tab_number', [301, 302, 303])->delete();

        $response = $this->actingAs($admin)->post('/tabs/bulk-store', [
            'game_config_id' => $config->id,
            'denomination'   => 0.0500,
            'lock_status'    => 'locked',
            'tabs'           => $customTabs,
        ]);

        $response->assertRedirect('/tabs');

        foreach ($customTabs as $item) {
            $tab = Tab::where('tab_number', $item['tab_number'])->first();
            $this->assertNotNull($tab);
            $this->assertEquals($item['table_name'], $tab->table_name);
            $this->assertEquals($item['ip_address'], $tab->ip_address);
            $this->assertEquals($config->id, $tab->game_config_id);
            $this->assertEquals('locked', $tab->lock_status);
            $this->assertEquals(0.0500, (float)$tab->denomination);
        }
    }

    public function test_bulk_store_rejects_duplicate_tab_numbers_and_names()
    {
        $admin = $this->getAdmin();
        $config = GameConfig::where('is_active', 1)->first() ?? GameConfig::first();

        // 1. Duplicate within the submitted batch
        $batchWithDuplicates = [
            ['tab_number' => 401, 'table_name' => 'Dup-1'],
            ['tab_number' => 401, 'table_name' => 'Dup-2'], // Duplicate number
        ];

        $response = $this->actingAs($admin)->post('/tabs/bulk-store', [
            'game_config_id' => $config->id,
            'denomination'   => 0.0100,
            'tabs'           => $batchWithDuplicates,
        ]);

        $response->assertSessionHasErrors(['tabs']);

        // 2. Conflict with existing database record
        $existingTab = Tab::first();

        $batchWithDbConflict = [
            ['tab_number' => $existingTab->tab_number, 'table_name' => 'BrandNewName-999'],
        ];

        $responseConflict = $this->actingAs($admin)->post('/tabs/bulk-store', [
            'game_config_id' => $config->id,
            'denomination'   => 0.0100,
            'tabs'           => $batchWithDbConflict,
        ]);

        $responseConflict->assertSessionHasErrors(['tabs']);
    }

    public function test_tabs_created_in_bulk_can_be_edited_individually_afterwards()
    {
        $admin = $this->getAdmin();
        $configs = GameConfig::where('is_active', 1)->get();
        $configA = $configs->first();
        $configB = $configs->count() > 1 ? $configs->skip(1)->first() : $configA;

        $tabNum = 501;
        Tab::where('tab_number', $tabNum)->delete();

        // 1. Create in bulk
        $this->actingAs($admin)->post('/tabs/bulk-store', [
            'game_config_id' => $configA->id,
            'denomination'   => 0.0100,
            'naming_prefix'  => 'InitialName-',
            'start_number'   => $tabNum,
            'quantity'       => 1,
        ]);

        $tab = Tab::where('tab_number', $tabNum)->first();
        $this->assertNotNull($tab);
        $this->assertEquals("InitialName-{$tabNum}", $tab->table_name);
        $this->assertEquals($configA->id, $tab->game_config_id);

        // 2. Edit individually via PUT /tabs/{tab}
        $updateResponse = $this->actingAs($admin)->put("/tabs/{$tab->id}", [
            'tab_number'     => $tabNum,
            'table_name'     => "CustomizedIndividually-{$tabNum}",
            'ip_address'     => '192.168.99.55',
            'active_mac'     => 'AA:BB:CC:DD:EE:99',
            'denomination'   => 0.0200,
            'game_config_id' => $configB->id,
            'status'         => 1,
            'lock_status'    => 'break',
        ]);

        $updateResponse->assertRedirect('/tabs');

        $tab->refresh();
        $this->assertEquals("CustomizedIndividually-{$tabNum}", $tab->table_name);
        $this->assertEquals('192.168.99.55', $tab->ip_address);
        $this->assertEquals('AA:BB:CC:DD:EE:99', $tab->active_mac);
        $this->assertEquals($configB->id, $tab->game_config_id);
        $this->assertEquals(0.0200, (float)$tab->denomination);
        $this->assertEquals('break', $tab->lock_status);
    }

    public function test_api_v1_tabs_bulk_creates_tabs_and_returns_json()
    {
        $config = GameConfig::where('is_active', 1)->first() ?? GameConfig::first();

        $startNum = 601;
        Tab::whereBetween('tab_number', [$startNum, $startNum + 2])->delete();

        $response = $this->postJson('/api/v1/tabs/bulk', [
            'game_config_id' => $config->id,
            'denomination'   => 0.0100,
            'naming_prefix'  => 'ApiBulk-',
            'start_number'   => $startNum,
            'quantity'       => 3,
            'start_ip'       => '10.20.30.1',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'count'   => 3,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'count',
            'data',
        ]);

        $this->assertDatabaseHas('tabs', [
            'tab_number' => 601,
            'table_name' => 'ApiBulk-601',
            'ip_address' => '10.20.30.1',
        ]);
        $this->assertDatabaseHas('tabs', [
            'tab_number' => 603,
            'table_name' => 'ApiBulk-603',
            'ip_address' => '10.20.30.3',
        ]);
    }
}
