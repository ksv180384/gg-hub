<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('characters')) {
            return;
        }
        Schema::table('characters', function (Blueprint $table) {
            if (!Schema::hasColumn('characters', 'is_main')) {
                $table->boolean('is_main')->default(false)->after('avatar');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('characters', 'is_main')) {
            Schema::table('characters', function (Blueprint $table) {
                $table->dropColumn('is_main');
            });
        }
    }
};
