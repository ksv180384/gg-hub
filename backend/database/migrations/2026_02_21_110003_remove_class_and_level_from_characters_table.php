<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('characters', 'class')) {
                $columns[] = 'class';
            }
            if (Schema::hasColumn('characters', 'level')) {
                $columns[] = 'level';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('class')->nullable()->after('name');
            $table->unsignedInteger('level')->nullable()->after('class');
        });
    }
};
