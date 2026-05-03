<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_guild_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('guild_id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['guild_id', 'character_id', 'tag_id']);
            $table->foreign('guild_id')->references('id')->on('guilds')->cascadeOnDelete();
            $table->foreign('character_id')->references('id')->on('characters')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_guild_tag');
    }
};
