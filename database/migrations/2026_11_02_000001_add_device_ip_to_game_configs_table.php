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
            if (!Schema::hasColumn('game_configs', 'device_ip')) {
                $table->string('device_ip', 45)->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_configs', function (Blueprint $table) {
            if (Schema::hasColumn('game_configs', 'device_ip')) {
                $table->dropColumn('device_ip');
            }
        });
    }
};
