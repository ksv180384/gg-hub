<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('guilds', 'deleted_at')) {
            Schema::table('guilds', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::table('guilds', function (Blueprint $table) {
            $table->index('server_id', 'guilds_server_id_fk_index');
        });

        Schema::table('guilds', function (Blueprint $table) {
            $table->dropUnique('guilds_server_id_slug_unique');
            $table->index(['server_id', 'slug'], 'guilds_server_id_slug_index');
        });

        Schema::table('guilds', function (Blueprint $table) {
            $table->dropIndex('guilds_server_id_fk_index');
        });
    }

    public function down(): void
    {
        Schema::table('guilds', function (Blueprint $table) {
            $table->dropIndex('guilds_server_id_slug_index');
            $table->unique(['server_id', 'slug'], 'guilds_server_id_slug_unique');
            $table->dropSoftDeletes();
        });
    }
};
