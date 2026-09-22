<?php

use App\Models\GameTable;
use App\Models\GameType;
use App\Models\TableFloat;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can open a game table, close it, and reopen it on the same day', function () {
    // 1. Create a GameType
    $gameType = GameType::create([
        'name' => 'Baccarat',
        'code' => 'BAC',
        'status' => true,
    ]);

    // 2. Create a GameTable
    $gameTable = GameTable::create([
        'table_name' => 'Test Table 1',
        'game_type_id' => $gameType->id,
        'float' => 50000.00,
        'status' => true,
    ]);

    $gameday = '2026-06-22';

    // 3. Open the table (first time)
    $response = $this->postJson("/api/v1/tables/{$gameTable->id}/open", [
        'opened_by' => 'Dealer 1',
        'gameday' => $gameday,
        'float_open' => 50000.00,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('session.status', 'open');
    $response->assertJsonPath('session.float_open', 50000);

    // Verify database has 1 record
    $this->assertDatabaseCount('table_floats', 1);

    // 4. Attempting to open it again while open should fail
    $response = $this->postJson("/api/v1/tables/{$gameTable->id}/open", [
        'opened_by' => 'Dealer 1',
        'gameday' => $gameday,
        'float_open' => 50000,
    ]);
    $response->assertStatus(422);

    // 5. Close the table
    $response = $this->postJson("/api/v1/tables/{$gameTable->id}/close", [
        'closed_by' => 'Dealer 1',
        'gameday' => $gameday,
        'float_close' => 51200,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('session.status', 'closed');
    $response->assertJsonPath('session.float_close', 51200);

    // 6. Reopen the table on the same day
    $response = $this->postJson("/api/v1/tables/{$gameTable->id}/open", [
        'opened_by' => 'Dealer 2',
        'gameday' => $gameday,
        'float_open' => 50500,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('session.status', 'open');
    $response->assertJsonPath('session.float_open', 50500);
    $response->assertJsonPath('session.float_close', null);
    $response->assertJsonPath('session.opened_by', 'Dealer 2');

    // Verify database has exactly 2 records (a new row was inserted)
    $this->assertDatabaseCount('table_floats', 2);

    $sessions = TableFloat::orderBy('float_id', 'asc')->get();
    $this->assertEquals('Dealer 1', $sessions[0]->opened_by);
    $this->assertEquals(50000.00, $sessions[0]->float_open);
    $this->assertEquals(51200.00, $sessions[0]->float_close);
    $this->assertEquals(0, $sessions[0]->status);

    $this->assertEquals('Dealer 2', $sessions[1]->opened_by);
    $this->assertEquals(50500.00, $sessions[1]->float_open);
    $this->assertNull($sessions[1]->float_close);
    $this->assertEquals(1, $sessions[1]->status);
});
