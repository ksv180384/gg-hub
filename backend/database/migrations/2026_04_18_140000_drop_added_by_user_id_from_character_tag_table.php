<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('character_tag')) {
            return;
        }
        if (! Schema::hasColumn('character_tag', 'added_by_user_id')) {
            return;
        }
        Schema::table('character_tag', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_by_user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('character_tag')) {
            return;
        }
        if (Schema::hasColumn('character_tag', 'added_by_user_id')) {
            return;
        }
        Schema::table('character_tag', function (Blueprint $table) {
            $table->foreignId('added_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
