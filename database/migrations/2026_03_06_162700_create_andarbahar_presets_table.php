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
        Schema::create('andarbahar_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('min_bet');
            $table->string('max_bet');
            $table->integer('burn_card')->nullable();
            // $table->boolean('enable_super_andar')->default(0);
            // $table->boolean('enable_super_bahar')->default(0);
            $table->foreignId('chip_preset_id')->constrained('chips')->cascadeOnDelete();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('andarbahar_presets');
    }
};
