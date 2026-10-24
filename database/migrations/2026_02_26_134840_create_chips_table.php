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
        Schema::create('chips', function (Blueprint $table) {
            $table->id();

            $table->string('preset_name')->unique();
            $table->decimal('chip_1_value', 10, 2);
            $table->decimal('chip_2_value', 10, 2);
            $table->decimal('chip_3_value', 10, 2);
            $table->decimal('chip_4_value', 10, 2);
            $table->decimal('chip_5_value', 10, 2);
            $table->decimal('base_value', 10, 2);

            $table->boolean('status')->default(1); // soft delete flag

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chips');
    }
};
