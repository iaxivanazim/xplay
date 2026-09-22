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
        Schema::create('payout_rules', function (Blueprint $table) {
            $table->id('payout_id');

            $table->unsignedBigInteger('game_type_id');

            $table->string('bet_name');        // Example: Red, Black, Straight 1
            $table->string('bet_position')->nullable();    // Example: A1, B5, R12 (UI mapping)

            $table->decimal('payout_multiplier', 8, 3)->nullable(); // Example: 35.00

            $table->boolean('is_active')->default(1);
            $table->boolean('is_jackpot')->default(0);

            $table->timestamps();

            $table->foreign('game_type_id')
                ->references('id')
                ->on('game_types')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_rules');
    }
};
