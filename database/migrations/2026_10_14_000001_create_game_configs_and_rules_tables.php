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
        if (!Schema::hasTable('game_configs')) {
            Schema::create('game_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('game_type_id')->constrained('game_types')->cascadeOnDelete();
                $table->string('min_bet')->default('10');
                $table->string('max_bet')->default('1000');
                $table->decimal('side_min_bet', 12, 2)->nullable();
                $table->decimal('side_max_bet', 12, 2)->nullable();
                $table->unsignedTinyInteger('burn_card')->default(0);
                $table->string('roulette_type', 20)->nullable()->default('european');
                $table->boolean('baccarat_6_commission')->default(0);
                $table->decimal('commission', 5, 4)->default(0.0500);

                // Specific game bet limit fields
                $table->decimal('tie_min', 12, 2)->nullable();
                $table->decimal('tie_max', 12, 2)->nullable();
                $table->decimal('pair_min', 12, 2)->nullable();
                $table->decimal('pair_max', 12, 2)->nullable();
                $table->decimal('super6_min', 12, 2)->nullable();
                $table->decimal('super6_max', 12, 2)->nullable();
                $table->decimal('andar_min', 12, 2)->nullable();
                $table->decimal('andar_max', 12, 2)->nullable();
                $table->decimal('bahar_min', 12, 2)->nullable();
                $table->decimal('bahar_max', 12, 2)->nullable();

                $table->boolean('is_active')->default(1);
                $table->boolean('is_default')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('game_config_payout_rules')) {
            Schema::create('game_config_payout_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_config_id')->constrained('game_configs')->cascadeOnDelete();
                $table->foreignId('payout_id')->constrained('payout_rules', 'payout_id')->cascadeOnDelete();
                $table->boolean('is_active')->default(1);
                $table->decimal('payout_multiplier', 8, 3)->nullable();
                $table->timestamps();

                $table->unique(['game_config_id', 'payout_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_config_payout_rules');
        Schema::dropIfExists('game_configs');
    }
};
