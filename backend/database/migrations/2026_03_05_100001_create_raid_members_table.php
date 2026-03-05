<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raid_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raid_id')->constrained('raids')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('role', 50)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['raid_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raid_members');
    }
};
