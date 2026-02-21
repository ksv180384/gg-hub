<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('guild_roles')) {
            Schema::create('guild_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->unsignedInteger('priority')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('guild_members')) {
            Schema::create('guild_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
                $table->foreignId('character_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('guild_role_id')->nullable()->constrained('guild_roles')->cascadeOnDelete();
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_members');
        Schema::dropIfExists('guild_roles');
    }
};
