<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_game_class', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_class_id')->constrained()->cascadeOnDelete();
            $table->primary(['character_id', 'game_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_game_class');
    }
};
