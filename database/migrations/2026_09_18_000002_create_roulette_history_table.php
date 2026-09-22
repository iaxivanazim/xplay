<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the roulette_history table.
 *
 * Stores every game round result for Roulette stations.
 * The game station sends the winning number; xplay calculates
 * the winning bet type and payout from configured payout rules.
 *
 * Roulette round structure:
 *   - A single spin produces one winning_number (0–36 for European, 0/00–36 for American).
 *   - Each bet_position maps to a payout rule (STRAIGHT, SPLIT, RED, etc.).
 *   - Multiple bets per spin are stored as separate rows (one row per bet position).
 *   - All rows for the same spin share the same game_no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roulette_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_id')
                  ->constrained('game_tables');                     // Game station FK

            $table->string('game_no')->nullable()->index();         // Unique spin identifier, e.g. "ROL-20260918-0001"
            $table->string('tab_id')->nullable()->index();          // Player terminal identifier

            // Spin result
            $table->unsignedTinyInteger('winning_number');          // 0–36 (European) or 0,37=00 (American)
            $table->enum('winning_colour', ['red', 'black', 'green']); // green = 0 / 00
            $table->enum('roulette_type', ['european', 'american'])->default('european');

            // Bet detail (one row per bet placed on this spin)
            $table->text('bet_position');                           // e.g. STRAIGHT, SPLIT, RED, ODD — from payout_rules.bet_position
            $table->json('bet_numbers')->nullable();               // Numbers covered by this bet e.g. [1,2] for split, [1] for straight

            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);       // 0 if losing bet
            $table->decimal('current_credit', 12, 2)->default(0);   // Tab balance after this round

            $table->dateTime('date_time')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roulette_history');
    }
};
