<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_bank_item_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->foreignId('guild_bank_item_id')->constrained('guild_bank_items')->cascadeOnDelete();
            $table->foreignId('received_by_character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('granted_by_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->text('reason');
            $table->dateTime('granted_at');
            $table->timestamps();

            // MariaDB/MySQL: limit 64 chars for index name, so use short explicit names.
            $table->index(['guild_id', 'guild_bank_item_id', 'granted_at'], 'gbig_guild_item_at_idx');
            $table->index(['guild_id', 'received_by_character_id', 'granted_at'], 'gbig_guild_recv_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_bank_item_grants');
    }
};

