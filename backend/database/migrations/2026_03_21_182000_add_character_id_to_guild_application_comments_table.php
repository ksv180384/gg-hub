<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('guild_application_comments') || Schema::hasColumn('guild_application_comments', 'character_id')) {
            return;
        }

        Schema::table('guild_application_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->nullable()->after('user_id');
        });

        DB::statement('
            UPDATE guild_application_comments c
            JOIN guild_applications a ON a.id = c.guild_application_id
            SET c.character_id = a.character_id
            WHERE c.character_id IS NULL
        ');

        Schema::table('guild_application_comments', function (Blueprint $table) {
            $table->foreign('character_id')
                ->references('id')
                ->on('characters')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('guild_application_comments') || ! Schema::hasColumn('guild_application_comments', 'character_id')) {
            return;
        }

        Schema::table('guild_application_comments', function (Blueprint $table) {
            $table->dropForeign(['character_id']);
            $table->dropColumn('character_id');
        });
    }
};
