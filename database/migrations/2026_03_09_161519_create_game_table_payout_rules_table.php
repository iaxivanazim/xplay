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
        Schema::create('game_table_payout_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables')->cascadeOnDelete();
            $table->foreignId('payout_id')->constrained('payout_rules', 'payout_id')->cascadeOnDelete();
            $table->boolean('is_active')->default(1);
            $table->decimal('seed_value', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['table_id', 'payout_id']); // one record per rule per table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_table_payout_rules');
    }
};
