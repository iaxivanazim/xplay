<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alters game_tables to add:
 *   - lock_status / locked_at / locked_by  (tab locking feature)
 *   - is_enabled                            (enable/disable independently of status)
 *   - denomination                          (replaces chip_preset; no Chips module in Virtuals)
 *   - last_seen_at                          (heartbeat tracking)
 *   - connection_status                     (portal-side connectivity flag)
 *
 * NOTE: 'status' column already exists (boolean, enabled/disabled).
 * We keep it for backward compatibility; is_enabled mirrors it going forward.
 * felt_color is left in place (harmless, nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_tables', function (Blueprint $table) {

            // ── Tab locking ────────────────────────────────────────────
            $table->enum('lock_status', ['unlocked', 'locked', 'break'])
                  ->default('unlocked')
                  ->after('status');

            $table->timestamp('locked_at')->nullable()->after('lock_status');

            $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();

            // ── Denomination (replaces chip_preset concept) ────────────
            // Base credit denomination for this tab. e.g. 1.00 = $1 per credit.
            $table->decimal('denomination', 10, 4)->default(1.0000)->after('locked_by');

            // ── Heartbeat / connectivity ───────────────────────────────
            $table->timestamp('last_seen_at')->nullable()->after('denomination');

            $table->enum('connection_status', ['online', 'offline', 'disconnected'])
                  ->default('offline')
                  ->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_tables', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn([
                'lock_status',
                'locked_at',
                'locked_by',
                'denomination',
                'last_seen_at',
                'connection_status',
            ]);
        });
    }
};
