<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('invited_by_character_id')->index()->nullable()->default(null)->after('character_id');
        });
        Schema::table('guild_applications', function (Blueprint $table) {
            $table->foreign('invited_by_character_id')->references('id')->on('characters')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('guild_applications', function (Blueprint $table) {
            $table->dropForeign(['invited_by_character_id']);
        });
    }
};
