<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('revoked_by_character_id')->index()->nullable()->after('invited_by_character_id');
        });
        Schema::table('guild_applications', function (Blueprint $table) {
            $table->foreign('revoked_by_character_id')->references('id')->on('characters')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('guild_applications', function (Blueprint $table) {
            $table->dropForeign(['revoked_by_character_id']);
        });
    }
};
