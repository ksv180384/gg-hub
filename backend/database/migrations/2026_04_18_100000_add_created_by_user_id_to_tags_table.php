<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tags', 'used_by_user_id') && Schema::hasColumn('tags', 'created_by_user_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->renameColumn('created_by_user_id', 'used_by_user_id');
            });
        }

        Schema::table('tags', function (Blueprint $table) {
            if (! Schema::hasColumn('tags', 'used_by_guild_id')) {
                $table->foreignId('used_by_guild_id')->nullable()->constrained('guilds')->nullOnDelete();
            }
        });

        Schema::table('tags', function (Blueprint $table) {
            if (! Schema::hasColumn('tags', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('tags', 'used_by_user_id') && Schema::hasColumn('tags', 'created_by_user_id')) {
            DB::table('tags')
                ->whereNull('created_by_user_id')
                ->whereNotNull('used_by_user_id')
                ->update(['created_by_user_id' => DB::raw('used_by_user_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            if (Schema::hasColumn('tags', 'created_by_user_id')) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            }
        });
    }
};
