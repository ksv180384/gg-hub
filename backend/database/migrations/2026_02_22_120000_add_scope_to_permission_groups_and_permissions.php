<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->string('scope', 20)->default('site')->after('id');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('scope', 20)->default('site')->after('id');
        });

        // Уникальность слага в рамках scope
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['scope', 'slug']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['scope', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->dropUnique(['scope', 'slug']);
            $table->unique('slug');
        });
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->dropColumn('scope');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['scope', 'slug']);
            $table->unique('slug');
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
