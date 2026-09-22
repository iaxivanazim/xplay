<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifies table_ledgers to only BUYIN and CASHOUT transactions.
 *
 * In the Virtuals cage system, ALL monetary flow goes through the cage.
 * Only two transaction types exist:
 *   BUYIN   — Cashier receives cash from player, credits the tab
 *   CASHOUT — Player cashes out; cashier pays cash back
 *
 * Also adds:
 *   session_id  — FK to tab_sessions (ties txn to a session)
 *   otp         — OTP active at time of transaction (snapshot)
 *
 * Removes: FILL, CREDIT, DROP, ADJUST, PAYOUT, VOID, BET, CHIPS payment_medium
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_ledgers', function (Blueprint $table) {

            // Add session linkage
            $table->unsignedBigInteger('session_id')->nullable()->after('tab_id');
            $table->foreign('session_id')
                  ->references('session_id')
                  ->on('tab_sessions')
                  ->nullOnDelete();

            $table->string('otp', 10)->nullable()->after('session_id');

            // Change txn_type enum to only BUYIN / CASHOUT
            // MySQL ALTER COLUMN on enum requires re-declaration
            // We use a raw statement to avoid doctrine/dbal dependency
        });

        // Modify enum column via raw SQL (DBAL-independent)
        \DB::statement("ALTER TABLE table_ledgers MODIFY txn_type ENUM('BUYIN','CASHOUT') NOT NULL");

        // Remove payment_medium (no chips concept) — set to nullable first for safety
        \DB::statement("ALTER TABLE table_ledgers MODIFY payment_medium ENUM('CASH') NULL");
    }

    public function down(): void
    {
        // Restore original enum values
        \DB::statement("ALTER TABLE table_ledgers MODIFY txn_type ENUM('FILL','CREDIT','DROP','ADJUST','CASHOUT','BUYIN','PAYOUT','VOID','BET') NOT NULL");
        \DB::statement("ALTER TABLE table_ledgers MODIFY payment_medium ENUM('CASH','CHIPS') NULL");

        Schema::table('table_ledgers', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropColumn(['session_id', 'otp']);
        });
    }
};
