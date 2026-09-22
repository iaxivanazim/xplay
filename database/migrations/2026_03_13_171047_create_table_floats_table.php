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
        Schema::create('table_floats', function (Blueprint $table) {
            $table->id('float_id');
            $table->foreignId('table_id')->constrained('game_tables')->cascadeOnDelete();
            $table->decimal('float_open', 12, 2);
            $table->decimal('float_close', 12, 2)->nullable();
            $table->string('opened_by');           // dealer name/id from external system
            $table->string('closed_by')->nullable();
            $table->tinyInteger('status')->default(1); // 1=open, 0=closed
            $table->date('gameday');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // one active session per table per gameday
            // $table->unique(['table_id', 'gameday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_floats');
    }
};
