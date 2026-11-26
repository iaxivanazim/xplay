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
        Schema::table('game_configs', function (Blueprint $table) {
            if (Schema::hasColumn('game_configs', 'side_max_bet')) {
                $table->dropColumn('side_max_bet');
            }
            if (Schema::hasColumn('game_configs', 'side_min_bet')) {
                $table->dropColumn('side_min_bet');
            }
            if (!Schema::hasColumn('game_configs', 'andar_bahar_game_count')) {
                $table->unsignedInteger('andar_bahar_game_count')->nullable()->after('bahar_max');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_configs', function (Blueprint $table) {
            if (Schema::hasColumn('game_configs', 'andar_bahar_game_count')) {
                $table->dropColumn('andar_bahar_game_count');
            }
            if (!Schema::hasColumn('game_configs', 'side_min_bet')) {
                $table->decimal('side_min_bet', 12, 2)->nullable()->after('max_bet');
            }
            if (!Schema::hasColumn('game_configs', 'side_max_bet')) {
                $table->decimal('side_max_bet', 12, 2)->nullable()->after('side_min_bet');
            }
        });
    }
};
