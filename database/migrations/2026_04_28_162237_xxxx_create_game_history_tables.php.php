<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BACCARAT
        Schema::create('baccarat_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shoe_no')->nullable();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->json('player_cards');          // ["Ah","Kd","3c"]
            $table->json('banker_cards');
            $table->string('winner', 20);          // player/banker/tie
            $table->string('side_win', 100)->nullable(); // "player_pair,lucky6" pipe or JSON
            $table->string('bet_position', 50);    // player/banker/tie/player_pair etc
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // ANDAR BAHAR
        Schema::create('andarbahar_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->string('joker_card', 10);
            $table->json('andar_cards');
            $table->json('bahar_cards');
            $table->string('winner', 20);          // andar/bahar
            $table->string('side_win', 100)->nullable();
            $table->string('bet_position', 50);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // DRAGON TIGER
        Schema::create('dragontiger_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shoe_no')->nullable();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->string('dragon_card', 10);
            $table->string('tiger_card', 10);
            $table->string('winner', 20);          // dragon/tiger/tie
            $table->string('side_win', 100)->nullable();
            $table->string('bet_position', 50);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // THREE CARD POKER
        Schema::create('threecardpoker_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->json('player_cards');          // always 3 cards
            $table->json('dealer_cards');
            $table->string('winner', 20);          // player/dealer/tie
            $table->string('side_win', 100)->nullable();
            $table->string('bet_position', 50);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // BLACKJACK
        Schema::create('blackjack_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->json('player_cards');
            $table->json('dealer_cards');
            $table->decimal('double_amount', 12, 2)->default(0);
            $table->decimal('insurance_amount', 12, 2)->default(0);
            $table->json('split_hands')->nullable();  // null = no split
            $table->string('winner', 20);             // player/dealer/tie/blackjack/bust
            $table->string('side_win', 100)->nullable();
            $table->string('bet_position', 50);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // MINI FLUSH
        Schema::create('miniflush_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->json('player_cards');
            $table->json('dealer_cards');
            $table->string('winner', 20);
            $table->string('side_win', 100)->nullable();
            $table->string('bet_position', 50);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });

        // CASINO WAR
        Schema::create('casinowar_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('game_tables');
            $table->string('tab_id')->nullable()->index();
            $table->json('player_cards');
            $table->json('dealer_cards');
            $table->string('winner', 20);          // player/dealer/tie/war
            $table->string('side_win', 100)->nullable();
            $table->string('bet_position', 50);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('win_amount', 12, 2)->default(0);
            $table->decimal('current_credit', 12, 2)->default(0);
            $table->dateTime('date_time')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casinowar_history');
        Schema::dropIfExists('miniflush_history');
        Schema::dropIfExists('blackjack_history');
        Schema::dropIfExists('threecardpoker_history');
        Schema::dropIfExists('dragontiger_history');
        Schema::dropIfExists('andarbahar_history');
        Schema::dropIfExists('baccarat_history');
    }
};
