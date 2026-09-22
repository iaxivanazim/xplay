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
        Schema::create('game_table_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables')->cascadeOnDelete();
            $table->morphs('preset'); // generates preset_type + preset_id
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            // one config per table at a time
            $table->unique('table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_table_configs');
    }
};
