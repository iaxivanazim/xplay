<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates history tables for in-scope Virtuals games.
 * Out-of-scope games (Dragon Tiger, 3CP, Blackjack, Mini Flush, Casino War)
 * have been removed from this migration.
 *
 * Roulette history table will be added in a separate Phase 2 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── BACCARAT ─────────────────────────────────────────────────────────
        Schema::create('baccarat_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shoe_no')->nullable();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('game_no')->nullable()->index();
            $table->string('tab_id')->nullable()->index();
            $table->json('player_cards');           // ["Ah","Kd","3c"]
            $table->json('banker_cards');
            $table->text('winner');                 // player / banker / tie
            $table->text('side_win')->nullable();   // "player_pair,lucky6" or null
            $table->text('bet_position');           // player / banker / tie / player_pair …
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // ── ANDAR BAHAR ──────────────────────────────────────────────────────
        Schema::create('andarbahar_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('game_no')->nullable()->index();
            $table->string('tab_id')->nullable()->index();
            $table->string('joker_card', 10);
            $table->json('andar_cards');
            $table->json('bahar_cards');
            $table->text('winner');                 // andar / bahar
            $table->text('side_win')->nullable();
            $table->text('bet_position');
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andarbahar_history');
        Schema::dropIfExists('baccarat_history');
    }
};
