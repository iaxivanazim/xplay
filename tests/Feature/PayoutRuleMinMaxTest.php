<?php

namespace Tests\Feature;

use App\Models\GameConfig;
use App\Models\GameConfigPayoutRule;
use App\Models\GameType;
use App\Models\PayoutRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tab;
use App\Models\User;
use Tests\TestCase;

class PayoutRuleMinMaxTest extends TestCase
{
    protected User $user;
    protected GameType $gameType;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $permCreate = Permission::firstOrCreate(['slug' => 'create-payout_rules'], ['name' => 'Create Payout Rules', 'module' => 'payout_rules']);
        $permEdit = Permission::firstOrCreate(['slug' => 'edit-payout_rules'], ['name' => 'Edit Payout Rules', 'module' => 'payout_rules']);
        $permView = Permission::firstOrCreate(['slug' => 'view-payout_rules'], ['name' => 'View Payout Rules', 'module' => 'payout_rules']);
        $adminRole->permissions()->syncWithoutDetaching([$permCreate->id, $permEdit->id, $permView->id]);

        $this->user = User::where('username', 'admin')->first() ?? User::factory()->create(['role_id' => $adminRole->id]);
        $this->user->update(['role_id' => $adminRole->id]);

        $this->gameType = GameType::first() ?? GameType::create([
            'name'   => 'Baccarat',
            'code'   => 'BACCARAT',
            'status' => 1,
        ]);
    }

    public function test_payout_rule_model_stores_pipe_separated_and_single_min_max(): void
    {
        $rulePipe = PayoutRule::create([
            'game_type_id'      => $this->gameType->id,
            'bet_name'          => 'Test Pipe Rule',
            'bet_position'      => 'TP1',
            'payout_multiplier' => 2.5,
            'min'               => '10|25|50',
            'max'               => '500|1000|2000',
            'is_active'         => 1,
        ]);

        $this->assertEquals('10|25|50', $rulePipe->min);
        $this->assertEquals('500|1000|2000', $rulePipe->max);
        $this->assertEquals([10.0, 25.0, 50.0], $rulePipe->min_values);
        $this->assertEquals([500.0, 1000.0, 2000.0], $rulePipe->max_values);

        $ruleSingle = PayoutRule::create([
            'game_type_id'      => $this->gameType->id,
            'bet_name'          => 'Test Single Rule',
            'bet_position'      => 'TS1',
            'payout_multiplier' => 5.0,
            'min'               => '5',
            'max'               => '100',
            'is_active'         => 1,
        ]);

        $this->assertEquals('5', $ruleSingle->min);
        $this->assertEquals('100', $ruleSingle->max);
        $this->assertEquals([5.0], $ruleSingle->min_values);
        $this->assertEquals([100.0], $ruleSingle->max_values);
    }

    public function test_payout_rule_store_and_update_controllers_accept_min_and_max(): void
    {
        PayoutRule::where('bet_position', 'NWR')->delete();

        $response = $this->actingAs($this->user)->postJson('/payout_rules/store', [
            'game_type_id'      => $this->gameType->id,
            'bet_name'          => 'New Web Rule',
            'bet_position'      => 'NWR',
            'payout_multiplier' => 1.5,
            'min'               => '20|50|100',
            'max'               => '1000|2500|5000',
            'is_active'         => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rule Added',
            ]);

        $created = PayoutRule::where('bet_position', 'NWR')->first();
        $this->assertNotNull($created);
        $this->assertEquals('20|50|100', $created->min);
        $this->assertEquals('1000|2500|5000', $created->max);

        // Test update
        $updateResponse = $this->actingAs($this->user)->postJson('/payout_rules/update/' . $created->payout_id, [
            'min' => '25',
            'max' => '2500',
        ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('25', $created->fresh()->min);
        $this->assertEquals('2500', $created->fresh()->max);
    }

    public function test_payout_rule_store_validates_pipe_separated_numbers(): void
    {
        $response = $this->actingAs($this->user)->postJson('/payout_rules/store', [
            'game_type_id'      => $this->gameType->id,
            'bet_name'          => 'Invalid Rule',
            'bet_position'      => 'INV',
            'payout_multiplier' => 1.0,
            'min'               => '10|abc|50',
            'max'               => '500',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min']);
    }

    public function test_api_by_game_type_includes_min_and_max(): void
    {
        $response = $this->getJson("/api/v1/payout-rules/game-type/{$this->gameType->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'count',
                'data' => [
                    '*' => [
                        'payout_id',
                        'game_type_id',
                        'bet_name',
                        'bet_position',
                        'payout_multiplier',
                        'min',
                        'max',
                        'is_active',
                    ]
                ]
            ]);

        $firstRule = $response->json('data.0');
        $this->assertArrayHasKey('min', $firstRule);
        $this->assertArrayHasKey('max', $firstRule);
    }

    public function test_api_show_includes_min_and_max(): void
    {
        $rule = PayoutRule::where('game_type_id', $this->gameType->id)->first();
        $this->assertNotNull($rule);

        $response = $this->getJson("/api/v1/payout-rules/rule/{$rule->payout_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'payout_id' => $rule->payout_id,
                    'min'       => $rule->min,
                    'max'       => $rule->max,
                ]
            ]);
    }

    public function test_api_payout_rules_by_game_type_closure_includes_min_and_max(): void
    {
        $response = $this->getJson("/api/v1/payout-rules/{$this->gameType->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('min', $data[0]);
        $this->assertArrayHasKey('max', $data[0]);
    }

    public function test_tab_api_configuration_includes_payout_rules_min_and_max(): void
    {
        $rule = PayoutRule::where('game_type_id', $this->gameType->id)->first();

        $config = GameConfig::firstOrCreate(
            ['name' => 'Test Payout Config'],
            [
                'game_type_id' => $this->gameType->id,
                'min_bet'      => '10|25',
                'max_bet'      => '500|1000',
                'is_active'    => 1,
                'is_default'   => 0,
            ]
        );

        GameConfigPayoutRule::firstOrCreate(
            [
                'game_config_id' => $config->id,
                'payout_id'      => $rule->payout_id,
            ],
            [
                'is_active'         => 1,
                'payout_multiplier' => 2.0,
            ]
        );

        $tab = Tab::firstOrCreate(
            ['tab_number' => 9922],
            [
                'table_name'        => 'Test Payout Tab',
                'game_type_id'      => $this->gameType->id,
                'game_config_id'    => $config->id,
                'denomination'      => 1.00,
                'status'            => 1,
                'lock_status'       => 'unlocked',
                'connection_status' => 'online',
            ]
        );

        if (!$tab->gameConfigs()->where('game_config_id', $config->id)->exists()) {
            $tab->gameConfigs()->attach($config->id, [
                'is_active'   => 1,
                'assigned_by' => $this->user->id,
                'assigned_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/v1/tabs/{$tab->id}/configuration");

        $response->assertStatus(200);
        $assignedGames = $response->json('assigned_games');
        $this->assertNotEmpty($assignedGames);

        $rules = $assignedGames[0]['payout_rules'];
        $this->assertNotEmpty($rules);
        $this->assertArrayHasKey('min', $rules[0]);
        $this->assertArrayHasKey('max', $rules[0]);
        $this->assertEquals($rule->min, $rules[0]['min']);
        $this->assertEquals($rule->max, $rules[0]['max']);
    }
}
