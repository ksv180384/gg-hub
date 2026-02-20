<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('localization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['game_id', 'localization_id', 'slug']);
        });

        Schema::create('server_group_server', function (Blueprint $table) {
            $table->foreignId('server_group_id')->constrained('server_groups')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->primary(['server_group_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_group_server');
        Schema::dropIfExists('server_groups');
    }
};
