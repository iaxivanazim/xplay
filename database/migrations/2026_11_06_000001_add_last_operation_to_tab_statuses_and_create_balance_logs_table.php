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
        Schema::table('tab_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('tab_statuses', 'balance_version')) {
                $table->unsignedBigInteger('balance_version')->default(1)->after('current_balance');
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_type')) {
                $table->string('last_op_type', 30)->nullable()->after('balance_version'); // BUYIN, CASHOUT, WIN, LOSS, BET, ADJUST
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_amount')) {
                $table->decimal('last_op_amount', 12, 2)->default(0.00)->after('last_op_type');
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_prev_balance')) {
                $table->decimal('last_op_prev_balance', 12, 2)->default(0.00)->after('last_op_amount');
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_new_balance')) {
                $table->decimal('last_op_new_balance', 12, 2)->default(0.00)->after('last_op_prev_balance');
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_ref')) {
                $table->string('last_op_ref', 100)->nullable()->after('last_op_new_balance');
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_notes')) {
                $table->string('last_op_notes', 255)->nullable()->after('last_op_ref');
            }
            if (!Schema::hasColumn('tab_statuses', 'last_op_at')) {
                $table->timestamp('last_op_at')->nullable()->after('last_op_notes');
            }
        });

        if (!Schema::hasTable('tab_balance_logs')) {
            Schema::create('tab_balance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tab_id')->constrained('tabs')->cascadeOnDelete();
                $table->unsignedBigInteger('session_id')->nullable();
                $table->unsignedBigInteger('balance_version')->default(1);
                $table->string('op_type', 30); // BUYIN, CASHOUT, WIN, LOSS, BET, ADJUST
                $table->decimal('amount', 12, 2)->default(0.00);
                $table->decimal('prev_balance', 12, 2)->default(0.00);
                $table->decimal('new_balance', 12, 2)->default(0.00);
                $table->string('reference', 100)->nullable();
                $table->string('notes', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tab_id', 'created_at']);
                $table->index(['tab_id', 'balance_version']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tab_balance_logs');

        Schema::table('tab_statuses', function (Blueprint $table) {
            $cols = [
                'balance_version',
                'last_op_type',
                'last_op_amount',
                'last_op_prev_balance',
                'last_op_new_balance',
                'last_op_ref',
                'last_op_notes',
                'last_op_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tab_statuses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
