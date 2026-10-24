<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameDayController;
use App\Http\Controllers\GameTableController;
use App\Http\Controllers\PayoutRuleController;
use App\Http\Controllers\GameTypeController;
use App\Http\Controllers\TableLedgerController;
use App\Http\Controllers\GameHistoryController;
use App\Http\Controllers\userController;
use App\Http\Controllers\Api\TabApiController;
use App\Http\Controllers\Api\StationResultController;
use App\Models\GameType;
use App\Models\PayoutRule;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    // ── Tab Virtuals API Endpoints ──────────────────────────────────────────
    Route::get('/tabs',                        [TabApiController::class, 'index']);
    Route::get('/tabs/by-mac/{mac}',           [TabApiController::class, 'byMac']);
    Route::get('/tabs/{id}',                   [TabApiController::class, 'show'])->whereNumber('id');
    Route::post('/tabs/{id}/register-mac',     [TabApiController::class, 'registerMac'])->whereNumber('id');
    Route::get('/tabs/{id}/configuration',     [TabApiController::class, 'configuration'])->whereNumber('id');
    Route::get('/tabs/{id}/status',            [TabApiController::class, 'status'])->whereNumber('id');
    Route::post('/tabs/{id}/restore',          [TabApiController::class, 'restore'])->whereNumber('id'); // Failure recovery
    Route::post('/tabs/{id}/heartbeat',        [TabApiController::class, 'heartbeat'])->whereNumber('id');

    Route::get('/game-day/current', [GameDayController::class, 'current']);
    Route::post('/game-day/start', [GameDayController::class, 'start']);
    Route::post('/game-day/close', [GameDayController::class, 'close']);

    Route::get('/game-tables/active', [GameTableController::class, 'apiActive']);
    Route::get('/game-tables/by-mac/{mac}', [GameTableController::class, 'apiByMac']);
    Route::get('/game-tables/{id}/configuration', [GameTableController::class, 'apiConfiguration']);
    Route::post('/game-tables/{id}/register-mac', [GameTableController::class, 'registerMac']);

    Route::get('/game-tables', [GameTableController::class, 'apiIndex']);
    Route::get('/game-tables/{id}', [GameTableController::class, 'apiShow'])->whereNumber('id');

    Route::get('/game-tables/{id}/float', [GameTableController::class, 'currentFloat']);

    // routes/api.php
    Route::get('/game-tables/{id}/bet-index', [GameTableController::class, 'getBetIndex']);
    Route::post('/game-tables/{id}/bet-index', [GameTableController::class, 'setBetIndex']);

    Route::get('/payout-rules/game-type/{id}', [PayoutRuleController::class, 'apiByGameType']);
    // Route::get('/payout-rules/{id}', [PayoutRuleController::class, 'apiShow'])->whereNumber('id');

    // API route for fetching payout rules by game type (used in form JS)
    Route::get('/payout-rules/{gameTypeId}', function ($gameTypeId) {
        return PayoutRule::where('game_type_id', $gameTypeId)->get();
    });

    // API route for fetching game type config fields (used in form JS)
    Route::get('/game-types/{id}', function ($id) {
        return GameType::findOrFail($id);
    });

    Route::get('/game-types', [GameTypeController::class, 'apiIndex']);



    Route::post('/ledger/txn',                        [TableLedgerController::class, 'store'])->middleware('idempotent');
    Route::get('/ledger/table/{table_id}',            [TableLedgerController::class, 'byTable']);
    Route::get('/ledger/table/{table_id}/summary',    [TableLedgerController::class, 'summary']);
    Route::get('/ledger/tab/{tab_id}',                [TableLedgerController::class, 'byTab']);
    Route::get('/ledger/txn/{txn_id}',                [TableLedgerController::class, 'show']);
    Route::post('/ledger/txn/{txn_id}/claim',         [TableLedgerController::class, 'claim']);
    Route::post('/ledger/txn/{txn_id}/complete',      [TableLedgerController::class, 'complete']);
    Route::get('/ledger/pending',                     [TableLedgerController::class, 'pending']);

    // ── Station Middleware & Roulette Live Resulting ───────────────────────
    Route::post('/station/{tableId}/roulette/result', [StationResultController::class, 'receiveRouletteResult']);
    Route::post('/tabs/{id}/bet',                      [StationResultController::class, 'placeBet'])->whereNumber('id');
    Route::post('/roulette/settle',                   [StationResultController::class, 'settle']);

    Route::post('/history/{game}',           [GameHistoryController::class, 'store']);
    Route::get('/history/{game}/table/{id}', [GameHistoryController::class, 'byTable']);
    Route::get('/history/{game}/tab/{tabId}',  [GameHistoryController::class, 'byTab']);
    Route::get('/history/{game}/{recordId}', [GameHistoryController::class, 'show']);

    Route::get('/users', [userController::class, 'apiIndex']);

});

// Backward-compatible non-v1 aliases for Tab endpoints (/api/tabs/...)
Route::prefix('tabs')->group(function () {
    Route::get('/',                    [TabApiController::class, 'index']);
    Route::get('/by-mac/{mac}',       [TabApiController::class, 'byMac']);
    Route::get('/{id}',               [TabApiController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/register-mac', [TabApiController::class, 'registerMac'])->whereNumber('id');
    Route::get('/{id}/configuration', [TabApiController::class, 'configuration'])->whereNumber('id');
    Route::get('/{id}/status',        [TabApiController::class, 'status'])->whereNumber('id');
    Route::post('/{id}/restore',      [TabApiController::class, 'restore'])->whereNumber('id');
    Route::post('/{id}/heartbeat',    [TabApiController::class, 'heartbeat'])->whereNumber('id');
    Route::post('/{id}/bet',          [StationResultController::class, 'placeBet'])->whereNumber('id');
});

// https://documenter.getpostman.com/view/31035377/2sBXcEmMLA