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
        Schema::table('tabs', function (Blueprint $table) {
            $table->decimal('float', 12, 2)->default(0.00)->nullable()->change();
            $table->unsignedInteger('bet_index')->default(1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tabs', function (Blueprint $table) {
            $table->decimal('float', 12, 2)->nullable(false)->change();
        });
    }
};
