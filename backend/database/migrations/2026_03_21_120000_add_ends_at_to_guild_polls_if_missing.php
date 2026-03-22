<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guild_polls') && ! Schema::hasColumn('guild_polls', 'ends_at')) {
            Schema::table('guild_polls', function (Blueprint $table) {
                $table->dateTime('ends_at')->nullable()->after('closed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('guild_polls') && Schema::hasColumn('guild_polls', 'ends_at')) {
            Schema::table('guild_polls', function (Blueprint $table) {
                $table->dropColumn('ends_at');
            });
        }
    }
};
