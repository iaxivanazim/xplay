<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\GameTypeSeeder;
use Database\Seeders\PayoutRuleSeeder;

class ResetService
{
    /**
     * In-scope game history tables (Virtuals: Baccarat, Andar Bahar, Roulette).
     */
    private const HISTORY_TABLES = [
        'baccarat_history',
        'andarbahar_history',
        'roulette_history',
    ];

    /**
     * In-scope preset tables (game configuration).
     */
    private const PRESET_TABLES = [
        'baccarat_presets',
        'andarbahar_presets',
        'roulette_presets',
    ];

    /**
     * Session / ledger tables cleared on API reset.
     */
    private const SESSION_TABLES = [
        'table_ledgers',
        'tab_sessions',
        'tab_statuses',
    ];

    // ─────────────────────────────────────────────────────────────
    // RESET TYPE 1 — API DATA RESET
    // Clears all runtime/transactional data produced by the API:
    //   game_days, table_floats, table_ledgers, all history tables.
    // Resets game_tables.bet_index and active_mac.
    // Configuration (presets, payout rules, chips, users) is kept.
    //
    // Uses DELETE (not TRUNCATE) so the operation is fully
    // transactional in MySQL. TRUNCATE is DDL and causes an
    // implicit commit that would silently break the transaction.
    // ─────────────────────────────────────────────────────────────
    public function apiDataReset(): array
    {
        $counts = [];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::transaction(function () use (&$counts) {

                // History tables
                foreach (self::HISTORY_TABLES as $table) {
                    $counts[$table] = DB::table($table)->count();
                    DB::table($table)->delete();
                }

                // Financial / session tables
                $counts['table_ledgers'] = DB::table('table_ledgers')->count();
                DB::table('table_ledgers')->delete();

                $counts['table_floats'] = DB::table('table_floats')->count();
                DB::table('table_floats')->delete();

                $counts['game_days'] = DB::table('game_days')->count();
                DB::table('game_days')->delete();

                // Reset live state on game tables (bet index + MAC registration)
                DB::table('game_tables')->update([
                    'bet_index'  => 1,
                    'active_mac' => null,
                ]);
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $counts;
    }

    // ─────────────────────────────────────────────────────────────
    // RESET TYPE 2 — FULL DB RESET
    // Everything in apiDataReset PLUS all config and master tables.
    // Flow:
    //   1. DELETE all rows (inside a transaction, FK-safe)
    //   2. TRUNCATE all tables (DDL, resets AUTO_INCREMENT — outside tx)
    //   3. Run each seeder directly (not via Artisan::call)
    // ─────────────────────────────────────────────────────────────
    public function fullDbReset(): array
    {
        $counts = [];

        // Step 1 — delete all rows inside a transaction
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::transaction(function () use (&$counts) {

                // Transactional data
                foreach (self::HISTORY_TABLES as $table) {
                    $counts[$table] = DB::table($table)->count();
                    DB::table($table)->delete();
                }

                $counts['table_ledgers'] = DB::table('table_ledgers')->count();
                DB::table('table_ledgers')->delete();

                $counts['table_floats'] = DB::table('table_floats')->count();
                DB::table('table_floats')->delete();

                $counts['game_days'] = DB::table('game_days')->count();
                DB::table('game_days')->delete();

                // Configuration data
                $counts['game_table_configs'] = DB::table('game_table_configs')->count();
                DB::table('game_table_configs')->delete();

                $counts['game_table_payout_rules'] = DB::table('game_table_payout_rules')->count();
                DB::table('game_table_payout_rules')->delete();

                foreach (self::PRESET_TABLES as $table) {
                    $counts[$table] = DB::table($table)->count();
                    DB::table($table)->delete();
                }

                $counts['game_tables'] = DB::table('game_tables')->count();
                DB::table('game_tables')->delete();

                // Master / reference data
                DB::table('permission_role')->delete();
                DB::table('role_user')->delete();
                DB::table('permissions')->delete();
                DB::table('roles')->delete();
                DB::table('users')->delete();
                DB::table('payout_rules')->delete();
                DB::table('chips')->delete();
                DB::table('game_types')->delete();
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Step 2 — TRUNCATE to reset AUTO_INCREMENT counters (DDL, outside tx)
        $this->resetAutoIncrements();

        // Step 3 — Re-seed by calling each seeder's run() directly.
        // Artisan::call('db:seed') is unreliable inside a web request context.
        $this->runSeeders();

        return $counts;
    }

    /**
     * Returns a snapshot of current record counts for the pre-flight summary.
     */
    public function snapshot(): array
    {
        $tables = array_merge(
            self::HISTORY_TABLES,
            [
                'table_ledgers', 'table_floats', 'game_days', 'game_tables',
                'game_table_configs', 'game_table_payout_rules',
            ]
        );

        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->count();
        }

        return $snapshot;
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * TRUNCATE each table to reset AUTO_INCREMENT to 1.
     * Called outside any transaction since TRUNCATE is DDL in MySQL.
     */
    private function resetAutoIncrements(): void
    {
        $tables = array_merge(
            self::HISTORY_TABLES,
            self::PRESET_TABLES,
            [
                'table_ledgers', 'table_floats', 'game_days',
                'game_table_configs', 'game_table_payout_rules', 'game_tables',
                'permission_role', 'role_user', 'permissions', 'roles',
                'users', 'payout_rules', 'chips', 'game_types',
            ]
        );

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::statement("TRUNCATE TABLE `{$table}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Invoke each seeder's run() method directly.
     * More reliable than Artisan::call('db:seed') inside a web request.
     */
    private function runSeeders(): void
    {
        (new RoleSeeder())->run();
        (new UserSeeder())->run();
        (new PermissionSeeder())->run();
        (new RolePermissionSeeder())->run();
        (new GameTypeSeeder())->run();
        (new PayoutRuleSeeder())->run();
    }
}
