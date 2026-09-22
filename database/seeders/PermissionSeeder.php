<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::insert([
            ['name' => 'view-users', 'slug' => 'view-users', 'module' => 'users'],
            ['name' => 'create-users', 'slug' => 'create-users', 'module' => 'users'],
            ['name' => 'edit-users', 'slug' => 'edit-users', 'module' => 'users'],
            ['name' => 'deactivate-users', 'slug' => 'deactivate-users', 'module' => 'users'],

            ['name' => 'view-roles', 'slug' => 'view-roles', 'module' => 'roles'],
            ['name' => 'create-roles', 'slug' => 'create-roles', 'module' => 'roles'],
            ['name' => 'edit-roles', 'slug' => 'edit-roles', 'module' => 'roles'],
            ['name' => 'delete-roles', 'slug' => 'delete-roles', 'module' => 'roles'],

            ['name' => 'assign-permissions', 'slug' => 'assign-permissions', 'module' => 'roles'],

            // ['name' => 'game-day-start', 'slug' => 'game-day-start', 'module' => 'game-day'],
            // ['name' => 'game-day-close', 'slug' => 'game-day-close', 'module' => 'game-day'],

            // ['name' => 'view-game_tables', 'slug' => 'view-game_tables', 'module' => 'game_tables'],
            ['name' => 'create-game_tables', 'slug' => 'create-game_tables', 'module' => 'game_tables'],
            // ['name' => 'edit-game_tables', 'slug' => 'edit-game_tables', 'module' => 'game_tables'],
            // ['name' => 'delete-game_tables', 'slug' => 'delete-game_tables', 'module' => 'game_tables'],

            // ['name' => 'view-game_types', 'slug' => 'view-game_types', 'module' => 'game_types'],
            // ['name' => 'create-game_types', 'slug' => 'create-game_types', 'module' => 'game_types'],
            // ['name' => 'edit-game_types', 'slug' => 'edit-game_types', 'module' => 'game_types'],
            // ['name' => 'delete-game_types', 'slug' => 'delete-game_types', 'module' => 'game_types'],

            // ['name' => 'view-themes', 'slug' => 'view-themes', 'module' => 'themes'],
            // ['name' => 'create-themes', 'slug' => 'create-themes', 'module' => 'themes'],
            // ['name' => 'delete-themes', 'slug' => 'delete-themes', 'module' => 'themes'],

            // ['name' => 'view-payout_rules', 'slug' => 'view-payout_rules', 'module' => 'payout_rules'],
            // ['name' => 'create-payout_rules', 'slug' => 'create-payout_rules', 'module' => 'payout_rules'],
            // ['name' => 'edit-payout_rules', 'slug' => 'edit-payout_rules', 'module' => 'payout_rules'],
            // ['name' => 'delete-payout_rules', 'slug' => 'delete-payout_rules', 'module' => 'payout_rules'],

            ['name' => 'view-chips', 'slug' => 'view-chips', 'module' => 'chips'],
            ['name' => 'edit-chips', 'slug' => 'edit-chips', 'module' => 'chips'],
            ['name' => 'create-chips', 'slug' => 'create-chips', 'module' => 'chips'],
            ['name' => 'delete-chips', 'slug' => 'delete-chips', 'module' => 'chips'],

            ['name' => 'view-history', 'slug' => 'view-history', 'module' => 'history'],

            ['name' => 'view-ledger', 'slug' => 'view-ledger', 'module' => 'ledger'],

            ['name' => 'manage-resets', 'slug' => 'manage-resets', 'module' => 'utilities'],
        ]);
    }
}
