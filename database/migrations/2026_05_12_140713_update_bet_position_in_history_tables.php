<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Updates bet_position and side_win to text, and adds game_no column
 * on in-scope history tables. Out-of-scope tables (DT, 3CP, BJ, MF, CW)
 * have been removed.
 *
 * NOTE: On fresh installs these columns are already correct in the create
 * migration — this migration is retained for existing databases only.
 */
return new class extends Migration
{
    protected $tables = [
        'baccarat_history',
        'andarbahar_history',
        'roulette_history',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'bet_position')) {
                    $table->text('bet_position')->change();
                }
                if (Schema::hasColumn($table->getTable(), 'side_win')) {
                    $table->text('side_win')->nullable()->change();
                }
                if (!Schema::hasColumn($table->getTable(), 'game_no')) {
                    $table->string('game_no')->nullable()->after('table_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'bet_position')) {
                    $table->string('bet_position')->change();
                }
                if (Schema::hasColumn($table->getTable(), 'side_win')) {
                    $table->string('side_win')->nullable()->change();
                }
                if (Schema::hasColumn($table->getTable(), 'game_no')) {
                    $table->dropColumn('game_no');
                }
            });
        }
    }
};
