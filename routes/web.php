<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\GameDayController;
use App\Http\Controllers\GameTableController;
use App\Http\Controllers\TabController;
use App\Http\Controllers\PayoutRuleController;
use App\Http\Controllers\ChipController;
use App\Http\Controllers\GameHistoryController;
use App\Http\Controllers\TableLedgerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/live-data', [DashboardController::class, 'liveData'])->name('dashboard.live-data');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');



    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:view-users');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->middleware('permission:create-users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('permission:create-users');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('permission:edit-users');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:edit-users');
    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate')->middleware('permission:deactivate-users');
    Route::patch('/users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->middleware('permission:deactivate-users');


    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:view-roles');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:create-roles');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:create-roles');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:edit-roles');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:edit-roles');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:delete-roles');


    Route::post('/game-day/start', [GameDayController::class, 'start'])->name('game-day.start')->middleware('permission:game-day-start');
    Route::post('/game-day/{id}/close', [GameDayController::class, 'close'])->name('game-day.close')->middleware('permission:game-day-close');


    Route::resource('/game_tables', GameTableController::class)->middleware('permission:create-game_tables');
    Route::post('/game_tables/{gameTable}/deactivate',    [GameTableController::class, 'deactivate'])->name('game_tables.deactivate');
    Route::post('/game_tables/{gameTable}/restore',       [GameTableController::class, 'restore'])->name('game_tables.restore');
    Route::post('/game_tables/{gameTable}/unregister-mac',[GameTableController::class, 'unregisterMac'])->name('game_tables.unregister-mac');

    // ── Tabs Configuration Module (Phase 3) ────────────────────────────────────
    Route::get('/tabs',                              [TabController::class, 'index'])->name('tabs.index');
    Route::get('/tabs/create',                       [TabController::class, 'create'])->name('tabs.create');
    Route::post('/tabs',                             [TabController::class, 'store'])->name('tabs.store');
    Route::get('/tabs/{tab}/edit',                   [TabController::class, 'edit'])->name('tabs.edit');
    Route::put('/tabs/{tab}',                        [TabController::class, 'update'])->name('tabs.update');
    Route::delete('/tabs/{tab}',                     [TabController::class, 'destroy'])->name('tabs.destroy');

    // Tab state management
    Route::post('/tabs/{tab}/enable',                [TabController::class, 'enable'])->name('tabs.enable');
    Route::post('/tabs/{tab}/disable',               [TabController::class, 'disable'])->name('tabs.disable');
    Route::post('/tabs/{tab}/lock',                  [TabController::class, 'lock'])->name('tabs.lock');
    Route::post('/tabs/{tab}/unlock',                [TabController::class, 'unlock'])->name('tabs.unlock');

    // MAC address management
    Route::post('/tabs/{tab}/register-mac',          [TabController::class, 'registerMac'])->name('tabs.register-mac');
    Route::post('/tabs/{tab}/unregister-mac',        [TabController::class, 'unregisterMac'])->name('tabs.unregister-mac');


    Route::get('/payout_rules', [PayoutRuleController::class, 'index'])->name('payout_rules.index')->middleware('permission:view-payout_rules');
    Route::get('/payout_rules/fetch/{id}', [PayoutRuleController::class, 'fetchByGameType'])->name('payout_rules.fetch')->middleware('permission:view-payout_rules');
    Route::post('/payout_rules/store', [PayoutRuleController::class, 'store'])->name('payout_rules.store')->middleware('permission:create-payout_rules');
    Route::post('/payout_rules/update/{id}', [PayoutRuleController::class, 'update'])->name('payout_rules.update')->middleware('permission:edit-payout_rules');
    Route::delete('/payout_rules/delete/{id}', [PayoutRuleController::class, 'destroy'])->name('payout_rules.delete')->middleware('permission:delete-payout_rules');

    Route::get('/chips', [ChipController::class, 'index'])->name('chips.index')->middleware('permission:view-chips');
    Route::post('/chips', [ChipController::class, 'store'])->name('chips.store')->middleware('permission:create-chips');
    Route::post('/chips/{id}', [ChipController::class, 'update'])->name('chips.update')->middleware('permission:edit-chips');
    Route::post('/chips/delete/{id}', [ChipController::class, 'destroy'])->name('chips.destroy')->middleware('permission:delete-chips');
    Route::get('/chips/{id}', [ChipController::class, 'show'])->name('chips.show')->middleware('permission:view-chips');
    Route::post('/chips/restore/{id}', [ChipController::class, 'restore'])->name('chips.restore')->middleware('permission:edit-chips');

    // Route::get('/history', [GameHistoryController::class, 'index'])->name('history.index')->middleware('permission:view-history');
    Route::get('/history',                  [GameHistoryController::class, 'index'])->name('history.index')->middleware('permission:view-history');
    Route::get('/ledger',                   [TableLedgerController::class, 'index'])->name('ledger.index')->middleware('permission:view-ledger');
    Route::get('/reports',                  [ReportController::class, 'index'])->name('reports.index');

    // ── Reset Utility ──────────────────────────────────────────────────────────
    Route::get('/utilities/reset',           [ResetController::class, 'index'])        ->name('utilities.reset')          ->middleware('permission:manage-resets');
    Route::post('/utilities/reset/api-data', [ResetController::class, 'apiDataReset'])->name('utilities.reset.api-data')  ->middleware('permission:manage-resets');
    Route::post('/utilities/reset/full-db',  [ResetController::class, 'fullDbReset']) ->name('utilities.reset.full-db')   ->middleware('permission:manage-resets');
});


require __DIR__ . '/auth.php';
