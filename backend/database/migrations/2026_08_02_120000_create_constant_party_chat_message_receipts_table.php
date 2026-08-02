<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_party_chat_message_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id');
            $table->foreignId('character_id');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('message_id', 'cp_chat_receipts_message_fk')
                ->references('id')
                ->on('constant_party_chat_messages')
                ->cascadeOnDelete();
            $table->foreign('character_id', 'cp_chat_receipts_character_fk')
                ->references('id')
                ->on('characters')
                ->cascadeOnDelete();
            $table->unique(['message_id', 'character_id'], 'cp_chat_receipts_message_character_unique');
            $table->index(['character_id', 'read_at'], 'cp_chat_receipts_character_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constant_party_chat_message_receipts');
    }
};
