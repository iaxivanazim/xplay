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
        Schema::table('payout_rules', function (Blueprint $table) {
            if (Schema::hasColumn('payout_rules', 'max')) {
                $table->dropColumn('max');
            }
            if (Schema::hasColumn('payout_rules', 'min')) {
                $table->dropColumn('min');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('payout_rules', 'min')) {
                $table->string('min')->nullable()->after('payout_multiplier');
            }
            if (!Schema::hasColumn('payout_rules', 'max')) {
                $table->string('max')->nullable()->after('min');
            }
        });
    }
};
