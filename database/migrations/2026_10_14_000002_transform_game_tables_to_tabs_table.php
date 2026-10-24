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
        // 1. Rename game_tables to tabs
        if (Schema::hasTable('game_tables') && !Schema::hasTable('tabs')) {
            Schema::rename('game_tables', 'tabs');
        }

        // 2. Add Tab-specific columns to tabs
        Schema::table('tabs', function (Blueprint $table) {
            if (!Schema::hasColumn('tabs', 'tab_number')) {
                $table->unsignedInteger('tab_number')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('tabs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('active_mac');
            }
            if (!Schema::hasColumn('tabs', 'game_config_id')) {
                $table->foreignId('game_config_id')->nullable()->after('game_type_id')->constrained('game_configs')->nullOnDelete();
            }
            if (!Schema::hasColumn('tabs', 'lock_status')) {
                $table->enum('lock_status', ['unlocked', 'locked', 'break'])->default('unlocked')->after('status');
            }
            if (!Schema::hasColumn('tabs', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('lock_status');
            }
            if (!Schema::hasColumn('tabs', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('tabs', 'denomination')) {
                $table->decimal('denomination', 10, 4)->default(0.0100)->after('locked_by');
            }
            if (!Schema::hasColumn('tabs', 'connection_status')) {
                $table->enum('connection_status', ['online', 'offline', 'disconnected'])->default('online')->after('denomination');
            }
            if (!Schema::hasColumn('tabs', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('connection_status');
            }
        });

        // 3. Create tab_game_configs (one tab can have multiple game_configs assigned)
        if (!Schema::hasTable('tab_game_configs')) {
            Schema::create('tab_game_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tab_id')->constrained('tabs')->cascadeOnDelete();
                $table->foreignId('game_config_id')->constrained('game_configs')->cascadeOnDelete();
                $table->boolean('is_active')->default(1);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamps();

                $table->unique(['tab_id', 'game_config_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tab_game_configs');

        if (Schema::hasTable('tabs')) {
            Schema::table('tabs', function (Blueprint $table) {
                $table->dropForeign(['game_config_id']);
                $table->dropForeign(['locked_by']);
                $table->dropColumn([
                    'tab_number', 'ip_address', 'game_config_id', 'lock_status',
                    'locked_at', 'locked_by', 'denomination', 'connection_status', 'last_seen_at'
                ]);
            });

            if (!Schema::hasTable('game_tables')) {
                Schema::rename('tabs', 'game_tables');
            }
        }
    }
};
