<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_party_chat_socket_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('constant_party_id');
            $table->foreignId('character_id');
            $table->foreignId('user_id');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('constant_party_id', 'cp_chat_tokens_party_fk')
                ->references('id')
                ->on('constant_parties')
                ->cascadeOnDelete();
            $table->foreign('character_id', 'cp_chat_tokens_character_fk')
                ->references('id')
                ->on('characters')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'cp_chat_tokens_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->index('expires_at', 'cp_chat_tokens_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constant_party_chat_socket_tokens');
    }
};
