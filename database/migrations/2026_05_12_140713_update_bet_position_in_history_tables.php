<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $tables = [
        'baccarat_history',
        'andarbahar_history',
        'dragontiger_history',
        'threecardpoker_history',
        'blackjack_history',
        'miniflush_history',
        'casinowar_history',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('bet_position')->change();
                $table->text('side_win')->nullable()->change();
                $table->string('game_no')->nullable()->after('table_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('bet_position')->change();
                $table->string('side_win')->nullable()->change();
                $table->dropColumn('game_no');
            });
        }
    }
};
