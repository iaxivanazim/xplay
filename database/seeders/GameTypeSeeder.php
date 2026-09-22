<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Scope: Virtuals tab-based games only — Baccarat, Andar Bahar, Roulette.
     */
    public function run(): void
    {
        DB::table('game_types')->insert([

            [
                'name'        => 'Baccarat',
                'code'        => 'BAC',
                'description' => 'A card game where players bet on the outcome of the player\'s hand, banker\'s hand, or a tie.',
                'created_at'  => now(),
                'updated_at'  => now()
            ],

            [
                'name'        => 'Andar Bahar',
                'code'        => 'AB',
                'description' => 'A simple card game where players bet on which side (Andar or Bahar) a card matching the value of the first card will appear.',
                'created_at'  => now(),
                'updated_at'  => now()
            ],

            [
                'name'        => 'Roulette',
                'code'        => 'ROL',
                'description' => 'A casino game where players bet on which numbered pocket a spinning ball will land in, with various bet types covering single numbers, groups, colours, or odd/even.',
                'created_at'  => now(),
                'updated_at'  => now()
            ],

        ]);
    }
}
