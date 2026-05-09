<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_bank_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('tier')->default(1);
            $table->string('color', 30)->nullable();
            $table->unsignedInteger('dkp_cost')->nullable();
            $table->timestamps();

            $table->index(['guild_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_bank_items');
    }
};

