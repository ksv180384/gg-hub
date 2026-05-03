<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_tag', function (Blueprint $table) {
            $table->foreignId('added_by_user_id')
                ->nullable()
                ->after('tag_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('character_tag', function (Blueprint $table) {
            $table->dropForeign(['added_by_user_id']);
        });
    }
};
