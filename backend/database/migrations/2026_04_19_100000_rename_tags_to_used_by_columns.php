<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tags', 'created_by_user_id') && ! Schema::hasColumn('tags', 'used_by_user_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->renameColumn('created_by_user_id', 'used_by_user_id');
            });
        }

        if (Schema::hasColumn('tags', 'created_by_guild_id') && ! Schema::hasColumn('tags', 'used_by_guild_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->renameColumn('created_by_guild_id', 'used_by_guild_id');
            });
        } elseif (! Schema::hasColumn('tags', 'used_by_guild_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->foreignId('used_by_guild_id')->nullable()->after('used_by_user_id')->constrained('guilds')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tags', 'used_by_guild_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->renameColumn('used_by_guild_id', 'created_by_guild_id');
            });
        }

        if (Schema::hasColumn('tags', 'used_by_user_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->renameColumn('used_by_user_id', 'created_by_user_id');
            });
        }
    }
};
