<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
    'name' => 'Admin',
    'username' => 'admin',
    'password' => bcrypt('admin@123'),
    'card_id' => 'ADMIN001',
    'role_id' => 1,
    'status' => 1
]);
    }
}
