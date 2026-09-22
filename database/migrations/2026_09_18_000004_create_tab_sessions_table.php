<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tab_sessions table.
 *
 * A session begins when a cashier processes a Buyin for a tab.
 * An OTP is generated and tied to that session.
 * The session ends on Cashout.
 *
 * One tab can have only ONE active session at a time.
 * Historical sessions are kept for audit and recovery purposes.
 *
 * Session states:
 *   active   — OTP issued, buyin done, player at terminal
 *   completed — Cashout processed, session closed normally
 *   voided   — Session cancelled by supervisor (no cashout)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tab_sessions', function (Blueprint $table) {
            $table->id('session_id');

            $table->foreignId('table_id')
                  ->constrained('game_tables')
                  ->cascadeOnDelete();

            // OTP: 6-digit numeric, issued at Buyin, verified by player
            $table->string('otp', 10)->index();

            // Financial
            $table->decimal('buyin_amount', 12, 2)->default(0);     // total cash-in for this session
            $table->decimal('cashout_amount', 12, 2)->default(0);   // total cash-out at session end
            $table->decimal('opening_balance', 12, 2)->default(0);  // balance at session start
            $table->decimal('closing_balance', 12, 2)->default(0);  // balance at session end

            // Cage staff
            $table->unsignedBigInteger('opened_by')->nullable();    // cashier who processed Buyin
            $table->unsignedBigInteger('closed_by')->nullable();    // cashier who processed Cashout
            $table->foreign('opened_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();

            // Session lifecycle
            $table->enum('status', ['active', 'completed', 'voided'])->default('active')->index();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            // Optional external reference (cage receipt / slip number)
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['table_id', 'status']);
            $table->index(['otp', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tab_sessions');
    }
};
