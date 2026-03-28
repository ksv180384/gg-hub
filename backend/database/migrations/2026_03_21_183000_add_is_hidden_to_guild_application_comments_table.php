<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guild_application_comments') || Schema::hasColumn('guild_application_comments', 'is_hidden')) {
            return;
        }

        Schema::table('guild_application_comments', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guild_application_comments') || ! Schema::hasColumn('guild_application_comments', 'is_hidden')) {
            return;
        }

        Schema::table('guild_application_comments', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
