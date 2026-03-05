<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_history_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_history_id')->constrained('event_histories')->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('external_name')->nullable();
            $table->timestamps();
        });

        Schema::create('event_history_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_history_id')->constrained('event_histories')->cascadeOnDelete();
            $table->string('url');
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_history_screenshots');
        Schema::dropIfExists('event_history_participants');
        Schema::dropIfExists('event_histories');
    }
};

