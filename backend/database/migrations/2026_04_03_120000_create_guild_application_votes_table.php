<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_application_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_application_id')->constrained('guild_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('vote'); // 1 = like, -1 = dislike
            $table->timestamps();

            $table->unique(['guild_application_id', 'user_id']);
            $table->index(['guild_application_id', 'vote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_application_votes');
    }
};
