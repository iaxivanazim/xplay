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
        Schema::create('table_ledgers', function (Blueprint $table) {
            $table->id('txn_id');
            $table->foreignId('table_id')->constrained('game_tables')->cascadeOnDelete();
            $table->string('tab_id')->nullable();          // player tab/session identifier
            $table->enum('txn_type', [
                'FILL',
                'CREDIT',
                'DROP',
                'ADJUST',
                'CASHOUT',
                'BUYIN',
                'PAYOUT',
                'VOID',
                'BET',
            ]);
            $table->enum('payment_medium',[
                'CASH',
                'CHIPS',
            ])->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('tab_balance', 12, 2)->default(0);   // running tab balance
            $table->decimal('float_balance', 12, 2);              // float after this txn
            $table->date('gameday');
            $table->tinyInteger('processed')->default(0);         // 0=new, 2=claimed, 1=done
            $table->string('reference')->nullable();              // external reference/note
            $table->string('initiated_by')->nullable();           // dealer/system
            $table->timestamps();

            $table->index(['table_id', 'gameday']);
            $table->index(['tab_id', 'gameday']);
            $table->index('processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_ledger');
    }
};
