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
        Schema::create('game_tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->unique();
            $table->foreignId('game_type_id')->constrained('game_types')->cascadeOnDelete();
            $table->string('active_mac')->unique()->nullable();
            $table->decimal('float', 19, 4);
            $table->unsignedTinyInteger('bet_index')->default(1);
            $table->boolean('status')->default(true);
            $table->string('felt_color')->nullable();
            // $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('game_tables');
        Schema::enableForeignKeyConstraints();
    }
};
