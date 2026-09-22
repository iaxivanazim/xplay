<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tab_statuses table — the recovery heartbeat table.
 *
 * ONE row per tab. Always reflects the CURRENT state of that tab.
 * This is the fastest path for failure recovery — terminal calls
 * GET /api/v1/tabs/{id}/status and receives the full recovery_data blob.
 *
 * Written atomically in the same DB::transaction() as every primary write:
 *   Buyin → update balance, session, OTP
 *   Game round → update last_game_no, last_bet, last_win, balance
 *   Cashout → reset session, zero balance
 *   Lock/Unlock → update status + lock_status
 *   Heartbeat → update last_synced_at, connection_status
 *
 * NEVER written from the terminal side — server is always the authority.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tab_statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_id')
                  ->unique()                                         // One row per tab
                  ->constrained('game_tables')
                  ->cascadeOnDelete();

            // ── Current tab status ─────────────────────────────────────
            $table->enum('status', ['idle', 'active', 'locked', 'break', 'disabled', 'disconnected'])
                  ->default('idle');

            $table->enum('lock_status', ['unlocked', 'locked', 'break'])
                  ->default('unlocked');

            // ── Session snapshot ───────────────────────────────────────
            $table->unsignedBigInteger('current_session_id')->nullable();
            $table->foreign('current_session_id')
                  ->references('session_id')
                  ->on('tab_sessions')
                  ->nullOnDelete();

            $table->string('current_otp', 10)->nullable();          // Active OTP or null if idle
            $table->timestamp('session_started_at')->nullable();

            // ── Financial snapshot ─────────────────────────────────────
            $table->decimal('current_balance', 12, 2)->default(0);  // Credits on tab RIGHT NOW
            $table->decimal('session_buyin', 12, 2)->default(0);    // Cumulative buyin this session
            $table->decimal('session_cashout', 12, 2)->default(0);  // Cumulative cashout this session

            // Shift-level aggregates (reset on new game day)
            $table->decimal('shift_total_in', 12, 2)->default(0);
            $table->decimal('shift_total_out', 12, 2)->default(0);
            $table->decimal('shift_total_bet', 12, 2)->default(0);
            $table->decimal('shift_total_win', 12, 2)->default(0);
            $table->unsignedInteger('shift_games_count')->default(0);
            $table->decimal('shift_last_bet', 12, 2)->default(0);

            // ── Game snapshot ──────────────────────────────────────────
            $table->string('last_game_no', 50)->nullable();
            $table->timestamp('last_game_at')->nullable();
            $table->decimal('last_bet_amount', 12, 2)->nullable();
            $table->decimal('last_win_amount', 12, 2)->nullable();

            // ── Recovery payload ───────────────────────────────────────
            // Full JSON blob: session + config + last_round + payout_rules
            // Terminal deserialises this on reconnect to restore from last known state
            $table->json('recovery_data')->nullable();

            // ── Sync tracking ──────────────────────────────────────────
            $table->timestamp('last_synced_at')->nullable();        // Last time this row was written
            $table->enum('connection_status', ['online', 'offline', 'disconnected'])
                  ->default('offline');

            // ── Hardware ───────────────────────────────────────────────
            $table->string('active_mac', 50)->nullable();           // Mirrors game_tables.active_mac

            $table->timestamps();

            $table->index('status');
            $table->index('connection_status');
            $table->index('current_otp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tab_statuses');
    }
};
