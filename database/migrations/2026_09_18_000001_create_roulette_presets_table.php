<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the roulette_presets table.
 *
 * Stores per-station configuration for Roulette game stations.
 * Linked polymorphically via game_table_configs (preset_type / preset_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roulette_presets', function (Blueprint $table) {
            $table->id();

            $table->string('name');                                  // Preset label, e.g. "Roulette Standard"

            $table->enum('roulette_type', ['european', 'american'])
                  ->default('european');                             // European = single zero | American = double zero

            // Bet limits (pipe-separated tiers, e.g. "100|500|1000")
            // Supports multiple bet-index tiers matching the bet_index on the game station
            $table->string('min_bet');
            $table->string('max_bet');

            // Side bet limits (optional — for special inside/outside bet caps)
            $table->string('side_min_bet')->nullable();
            $table->string('side_max_bet')->nullable();

            // Chip denomination preset
            $table->foreignId('chip_preset_id')
                  ->constrained('chips')
                  ->onDelete('restrict');

            $table->boolean('status')->default(1);                  // 1 = active, 0 = inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roulette_presets');
    }
};
