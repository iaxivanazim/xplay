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
        // 1. tab_sessions
        if (!Schema::hasTable('tab_sessions')) {
            Schema::create('tab_sessions', function (Blueprint $table) {
                $table->id('session_id');
                $table->foreignId('tab_id')->constrained('tabs')->cascadeOnDelete();
                $table->string('otp', 10);
                $table->string('player_name')->nullable();
                $table->string('player_phone', 50)->nullable();
                $table->string('player_card_id', 100)->nullable();
                $table->decimal('buyin_amount', 12, 2)->default(0);  // Cashin amount
                $table->decimal('cashout_amount', 12, 2)->default(0); // Cashout amount
                $table->decimal('opening_balance', 12, 2)->default(0);
                $table->decimal('closing_balance', 12, 2)->default(0);
                $table->decimal('total_bet', 12, 2)->default(0);
                $table->decimal('total_win', 12, 2)->default(0);
                $table->unsignedInteger('games_played')->default(0);
                $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['active', 'completed', 'voided'])->default('active');
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('closed_at')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tab_id', 'status']);
                $table->index('otp');
            });
        }

        // 2. tab_statuses (Failure Recovery & State Restoration)
        if (!Schema::hasTable('tab_statuses')) {
            Schema::create('tab_statuses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tab_id')->unique()->constrained('tabs')->cascadeOnDelete();
                $table->enum('status', ['idle', 'active', 'locked', 'break', 'disabled', 'disconnected'])->default('idle');
                $table->enum('lock_status', ['unlocked', 'locked', 'break'])->default('unlocked');
                $table->unsignedBigInteger('current_session_id')->nullable();
                $table->string('current_otp', 10)->nullable();
                $table->string('player_name')->nullable();
                $table->string('player_phone', 50)->nullable();
                $table->string('player_card_id', 100)->nullable();
                $table->timestamp('session_started_at')->nullable();
                $table->decimal('current_balance', 12, 2)->default(0.00); // Currency amount (no chips)
                $table->decimal('session_buyin', 12, 2)->default(0.00);
                $table->decimal('session_cashout', 12, 2)->default(0.00);
                $table->decimal('shift_total_in', 12, 2)->default(0.00);
                $table->decimal('shift_total_out', 12, 2)->default(0.00);
                $table->decimal('shift_total_bet', 12, 2)->default(0.00);
                $table->decimal('shift_total_win', 12, 2)->default(0.00);
                $table->unsignedInteger('shift_games_count')->default(0);
                $table->decimal('shift_last_bet', 12, 2)->default(0.00);
                $table->string('last_game_no', 50)->nullable();
                $table->timestamp('last_game_at')->nullable();
                $table->decimal('last_bet_amount', 12, 2)->default(0.00);
                $table->decimal('last_win_amount', 12, 2)->default(0.00);
                $table->longText('recovery_data')->nullable(); // JSON state snapshot for failure restoration
                $table->timestamp('last_synced_at')->nullable();
                $table->enum('connection_status', ['online', 'offline', 'disconnected'])->default('online');
                $table->string('active_mac', 50)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tab_statuses');
        Schema::dropIfExists('tab_sessions');
    }
};
