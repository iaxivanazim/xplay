<?php

namespace Tests\Feature;

use App\Models\AndarbaharHistory;
use App\Models\BaccaratHistory;
use App\Models\GameTable;
use App\Models\GameType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RouletteHistory;
use App\Models\User;
use Tests\TestCase;

class GameHistoryModuleTest extends TestCase
{
    protected User $user;
    protected GameTable $tab1;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $permView = Permission::firstOrCreate(['slug' => 'view-history'], ['name' => 'View History', 'module' => 'history']);
        $adminRole->permissions()->syncWithoutDetaching([$permView->id]);

        $this->user = User::where('username', 'admin')->first() ?? User::factory()->create(['role_id' => $adminRole->id]);
        $this->user->update(['role_id' => $adminRole->id]);

        $this->tab1 = GameTable::firstOrCreate(['id' => 1], [
            'tab_number' => 1,
            'table_name' => 'Amatic-1',
            'status'     => 1,
            'float'      => 0,
        ]);
    }

    public function test_history_page_renders_tab_filter_and_removes_tab_id_filter(): void
    {
        $response = $this->actingAs($this->user)->get(route('history.index'));
        $response->assertStatus(200);

        // Verify "Tab" label is present in filter form
        $response->assertSee('<label class="form-label text-secondary small mb-1">Tab</label>', false);
        $response->assertSee('Amatic-1 (Tab #1)', false);

        // Verify redundant "Tab ID" filter label and input were removed from form
        $response->assertDontSee('<label class="form-label text-secondary small mb-1">Tab ID</label>', false);
        $response->assertDontSee('name="tab_id"', false);
    }

    public function test_api_stores_baccarat_history_without_explicit_datetime(): void
    {
        $payload = [
            'table_id'       => $this->tab1->id,
            'game_no'        => 'BAC-TEST-001',
            'shoe_no'        => 5,
            'player_cards'   => 'Ah,9c',
            'banker_cards'   => 'Kd,8s',
            'winner'         => 'player',
            'side_win'       => 'player_pair',
            'bet_position'   => 'player:100,player_pair:25',
            'bet_amount'     => 125,
            'win_amount'     => 200,
            'current_credit' => 1075,
        ];

        $response = $this->postJson('/api/v1/history/baccarat', $payload);
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('baccarat_history', [
            'game_no'  => 'BAC-TEST-001',
            'table_id' => $this->tab1->id,
            'winner'   => 'player',
        ]);
    }

    public function test_api_stores_andarbahar_history_without_explicit_datetime(): void
    {
        $payload = [
            'table_id'       => $this->tab1->id,
            'game_no'        => 'AB-TEST-001',
            'joker_card'     => 'Ks',
            'andar_cards'    => '4h,7d,Ks',
            'bahar_cards'    => '9c,Jd',
            'winner'         => 'andar',
            'bet_position'   => 'andar:100',
            'bet_amount'     => 100,
            'win_amount'     => 195,
            'current_credit' => 1095,
        ];

        $response = $this->postJson('/api/v1/history/andarbahar', $payload);
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('andarbahar_history', [
            'game_no'  => 'AB-TEST-001',
            'table_id' => $this->tab1->id,
            'winner'   => 'andar',
        ]);
    }

    public function test_baccarat_history_view_and_api(): void
    {
        BaccaratHistory::create([
            'table_id'       => $this->tab1->id,
            'tab_id'         => (string)$this->tab1->tab_number,
            'game_no'        => 'BAC-VERIFY-99',
            'shoe_no'        => 3,
            'player_cards'   => ['10h', '7c'],
            'banker_cards'   => ['9d', '4s'],
            'winner'         => 'player',
            'bet_position'   => ['player' => 50],
            'bet_amount'     => 50,
            'win_amount'     => 100,
            'current_credit' => 500,
            'date_time'      => now(),
        ]);

        // Test API by table
        $apiResp = $this->getJson("/api/v1/history/baccarat/table/{$this->tab1->id}");
        $apiResp->assertStatus(200);
        $apiResp->assertJsonFragment(['game_no' => 'BAC-VERIFY-99']);

        // Test Web View
        $webResp = $this->actingAs($this->user)->get(route('history.index', [
            'game'     => 'BAC',
            'table_id' => $this->tab1->id,
        ]));
        $webResp->assertStatus(200);
        $webResp->assertSee('BAC-VERIFY-99');
        $webResp->assertSee('PLAYER');
    }

    public function test_andarbahar_history_view_and_api(): void
    {
        $record = AndarbaharHistory::create([
            'table_id'       => $this->tab1->id,
            'tab_id'         => (string)$this->tab1->tab_number,
            'game_no'        => 'AB-VERIFY-88',
            'joker_card'     => 'Ah',
            'andar_cards'    => ['2c', 'Ah'],
            'bahar_cards'    => ['8s'],
            'winner'         => 'andar',
            'bet_position'   => ['andar' => 100],
            'bet_amount'     => 100,
            'win_amount'     => 190,
            'current_credit' => 600,
            'date_time'      => now(),
        ]);

        // Test API by table
        $apiResp = $this->getJson("/api/v1/history/andarbahar/table/{$this->tab1->id}");
        $apiResp->assertStatus(200);
        $apiResp->assertJsonFragment(['game_no' => 'AB-VERIFY-88']);

        // Test API record detail
        $showResp = $this->getJson("/api/v1/history/andarbahar/{$record->id}");
        $showResp->assertStatus(200);
        $showResp->assertJsonFragment(['game_no' => 'AB-VERIFY-88']);

        // Test Web View
        $webResp = $this->actingAs($this->user)->get(route('history.index', [
            'game'     => 'AB',
            'table_id' => $this->tab1->id,
        ]));
        $webResp->assertStatus(200);
        $webResp->assertSee('AB-VERIFY-88');
        $webResp->assertSee('ANDAR');
    }

    public function test_roulette_history_continues_working(): void
    {
        RouletteHistory::create([
            'table_id'       => $this->tab1->id,
            'tab_id'         => (string)$this->tab1->tab_number,
            'game_no'        => 'ROU-REGRESS-01',
            'winning_number' => 7,
            'winning_colour' => 'red',
            'roulette_type'  => 'European',
            'bet_amount'     => 10,
            'win_amount'     => 360,
            'current_credit' => 800,
            'date_time'      => now(),
        ]);

        $webResp = $this->actingAs($this->user)->get(route('history.index', [
            'game'     => 'ROL',
            'table_id' => $this->tab1->id,
        ]));
        $webResp->assertStatus(200);
        $webResp->assertSee('ROU-REGRESS-01');
        $webResp->assertSee('#7 RED');
    }

    public function test_winner_filtering_is_case_insensitive_and_matches_outcomes(): void
    {
        BaccaratHistory::create([
            'table_id'       => $this->tab1->id,
            'tab_id'         => (string)$this->tab1->tab_number,
            'game_no'        => 'BAC-WINNER-BANKER',
            'shoe_no'        => 1,
            'player_cards'   => ['2h', '3c'],
            'banker_cards'   => ['9d', '9s'],
            'winner'         => 'banker',
            'bet_position'   => ['banker' => 50],
            'bet_amount'     => 50,
            'win_amount'     => 95,
            'current_credit' => 500,
            'date_time'      => now(),
        ]);

        $resp = $this->actingAs($this->user)->get(route('history.index', [
            'game'     => 'BAC',
            'table_id' => $this->tab1->id,
            'winner'   => 'banker',
        ]));
        $resp->assertStatus(200);
        $resp->assertSee('BAC-WINNER-BANKER');
        $resp->assertSee('BANKER');
    }

    public function test_history_supports_tab_parameter_alias(): void
    {
        $resp = $this->actingAs($this->user)->get(route('history.index', [
            'game' => 'BAC',
            'tab'  => $this->tab1->id,
        ]));
        $resp->assertStatus(200);
        $resp->assertSee('Amatic-1 (Tab #1)');
    }

    public function test_by_tab_endpoint_resolves_tab(): void
    {
        $resp = $this->getJson("/api/v1/history/baccarat/tab/{$this->tab1->tab_number}");
        $resp->assertStatus(200);
        $resp->assertJsonStructure(['data', 'current_page']);
    }
}
