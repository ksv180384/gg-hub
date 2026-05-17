<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_user_dkp_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->timestamps();

            $table->unique(['guild_id', 'user_id']);
        });

        Schema::create('guild_dkp_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamp('occurred_at');
            $table->string('source', 32);
            $table->foreignId('event_history_id')->nullable()->constrained('event_histories')->nullOnDelete();
            $table->foreignId('event_history_participant_id')->nullable()->constrained('event_history_participants')->nullOnDelete();
            $table->foreignId('guild_bank_item_grant_id')->nullable()->constrained('guild_bank_item_grants')->nullOnDelete();
            $table->foreignId('guild_bank_item_id')->nullable()->constrained('guild_bank_items')->nullOnDelete();
            $table->foreignId('character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->integer('balance_after');
            $table->timestamps();

            $table->index(['guild_id', 'occurred_at']);
            $table->index(['guild_id', 'user_id', 'occurred_at']);
            $table->index(['event_history_id', 'source']);
        });

        Schema::table('guild_bank_item_grants', function (Blueprint $table) {
            $table->unsignedInteger('dkp_charged')->nullable()->after('granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('guild_bank_item_grants', function (Blueprint $table) {
            $table->dropColumn('dkp_charged');
        });

        Schema::dropIfExists('guild_dkp_ledger_entries');
        Schema::dropIfExists('guild_user_dkp_balances');
    }
};
