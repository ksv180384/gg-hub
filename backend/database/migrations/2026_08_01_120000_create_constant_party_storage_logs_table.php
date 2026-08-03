<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_party_storage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id');
            $table->foreignId('item_id')->nullable();
            $table->foreignId('actor_character_id')->nullable();
            $table->foreignId('recipient_character_id')->nullable();
            $table->string('action', 32);
            $table->string('item_name');
            $table->string('actor_character_name');
            $table->string('recipient_character_name')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('constant_party_id', 'cp_logs_party_fk')
                ->references('id')->on('constant_parties')->cascadeOnDelete();
            $table->foreign('item_id', 'cp_logs_item_fk')
                ->references('id')->on('constant_party_storage_items')->nullOnDelete();
            $table->foreign('actor_character_id', 'cp_logs_actor_char_fk')
                ->references('id')->on('characters')->nullOnDelete();
            $table->foreign('recipient_character_id', 'cp_logs_recipient_char_fk')
                ->references('id')->on('characters')->nullOnDelete();

            $table->index(['constant_party_id', 'created_at'], 'cp_logs_party_created_idx');
            $table->index(['constant_party_id', 'action'], 'cp_logs_party_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constant_party_storage_logs');
    }
};
