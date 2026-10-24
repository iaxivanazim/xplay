<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('roulette_history')) {
            Schema::create('roulette_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('table_id')->nullable()->index();
                $table->string('game_no', 50)->nullable()->index();
                $table->string('tab_id', 50)->nullable()->index();
                $table->tinyInteger('winning_number')->unsigned();
                $table->string('winning_colour', 10); // 'red', 'black', 'green'
                $table->string('roulette_type', 30)->default('European');
                $table->json('bet_position')->nullable(); // structured bets array / map
                $table->json('bet_numbers')->nullable();  // numbers covered
                $table->decimal('bet_amount', 12, 2)->default(0.00);
                $table->decimal('win_amount', 12, 2)->default(0.00);
                $table->decimal('current_credit', 12, 2)->default(0.00);
                $table->json('round_bets')->nullable();   // optional round-wide bets snapshot
                $table->dateTime('date_time')->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roulette_history');
    }
};
