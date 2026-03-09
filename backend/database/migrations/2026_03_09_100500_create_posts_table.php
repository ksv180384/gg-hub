<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts')) {
            return;
        }

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->foreignId('guild_id')->nullable()->constrained('guilds')->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();

            $table->string('title')->nullable();
            $table->text('body');

            // Варианты видимости
            $table->boolean('is_visible_global')->default(false);
            $table->boolean('is_visible_guild')->default(false);

            // Настройки анонимности
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_global_as_guild')->default(false);

            // Статусы модерации по направлениям
            $table->string('status_global')->nullable(); // pending / approved / rejected и т.п.
            $table->string('status_guild')->nullable();

            // Даты публикации по направлениям
            $table->timestamp('published_at_global')->nullable();
            $table->timestamp('published_at_guild')->nullable();

            // Ранее спроектированные поля (на будущее, если понадобятся)
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

