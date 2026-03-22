<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('guild_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('guild_polls')->cascadeOnDelete();
            $table->string('text');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guild_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('guild_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('guild_poll_options')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_poll_votes');
        Schema::dropIfExists('guild_poll_options');
        Schema::dropIfExists('guild_polls');
    }
};
