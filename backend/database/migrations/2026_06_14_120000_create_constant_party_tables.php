<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('localization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('constant_party_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id')->constrained('constant_parties')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->boolean('can_manage_storage')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['constant_party_id', 'character_id'], 'cp_members_party_character_unique');
            $table->unique('character_id', 'cp_members_character_unique');
        });

        Schema::create('constant_party_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id')->constrained('constant_parties')->cascadeOnDelete();
            $table->foreignId('invited_character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('invited_by_character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['constant_party_id', 'status'], 'cp_invitations_party_status_index');
        });

        Schema::create('constant_party_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id')->constrained('constant_parties')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('constant_party_storage_item_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id')->constrained('constant_parties')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('constant_party_storage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id');
            $table->foreignId('tier_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->foreignId('created_by_character_id');
            $table->foreignId('updated_by_character_id')->nullable();
            $table->timestamps();

            $table->foreign('constant_party_id', 'cp_items_party_fk')
                ->references('id')->on('constant_parties')->cascadeOnDelete();
            $table->foreign('tier_id', 'cp_items_tier_fk')
                ->references('id')->on('constant_party_storage_item_tiers')->nullOnDelete();
            $table->foreign('created_by_character_id', 'cp_items_created_by_char_fk')
                ->references('id')->on('characters')->cascadeOnDelete();
            $table->foreign('updated_by_character_id', 'cp_items_updated_by_char_fk')
                ->references('id')->on('characters')->nullOnDelete();
        });

        Schema::create('constant_party_storage_item_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constant_party_id');
            $table->foreignId('item_id');
            $table->foreignId('received_by_character_id');
            $table->foreignId('granted_by_character_id');
            $table->text('reason')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->foreign('constant_party_id', 'cp_grants_party_fk')
                ->references('id')->on('constant_parties')->cascadeOnDelete();
            $table->foreign('item_id', 'cp_grants_item_fk')
                ->references('id')->on('constant_party_storage_items')->cascadeOnDelete();
            $table->foreign('received_by_character_id', 'cp_grants_received_char_fk')
                ->references('id')->on('characters')->cascadeOnDelete();
            $table->foreign('granted_by_character_id', 'cp_grants_granted_char_fk')
                ->references('id')->on('characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constant_party_storage_item_grants');
        Schema::dropIfExists('constant_party_storage_items');
        Schema::dropIfExists('constant_party_storage_item_tiers');
        Schema::dropIfExists('constant_party_chat_messages');
        Schema::dropIfExists('constant_party_invitations');
        Schema::dropIfExists('constant_party_members');
        Schema::dropIfExists('constant_parties');
    }
};
