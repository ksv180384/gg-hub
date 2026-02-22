<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table) {
            if (!Schema::hasColumn('guilds', 'leader_character_id')) {
                $table->foreignId('leader_character_id')->nullable()->after('owner_id')->constrained('characters')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('guilds', 'leader_character_id')) {
            Schema::table('guilds', function (Blueprint $table) {
                $table->dropForeign(['leader_character_id']);
            });
        }
    }
};
