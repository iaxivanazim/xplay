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
        Schema::create('game_days', function (Blueprint $table) {
            $table->id();
            $table->date('gaming_date'); // The accounting date (18th Feb)
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_hours')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->foreignId('started_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['gaming_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_days');
    }
};
