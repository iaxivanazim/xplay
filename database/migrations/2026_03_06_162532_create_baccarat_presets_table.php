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
        Schema::create('baccarat_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('min_bet');
            $table->string('max_bet');
            $table->integer('burn_card')->nullable();
            $table->decimal('side_min_bet', 10, 2)->nullable();
            $table->decimal('side_max_bet', 10, 2)->nullable();
            $table->boolean('commission')->default(1);
            $table->boolean('baccarat_6_commission')->default(1)->nullable();
            // $table->boolean('enable_pairbets')->default(0);
            // $table->boolean('enable_lucky6')->default(0);
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
        Schema::dropIfExists('baccarat_presets');
    }
};
