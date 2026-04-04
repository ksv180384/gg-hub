<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('post_comments') || Schema::hasColumn('post_comments', 'replied_to_comment_id')) {
            return;
        }

        Schema::table('post_comments', function (Blueprint $table) {
            $table->foreignId('replied_to_comment_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('post_comments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('post_comments', 'replied_to_comment_id')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('replied_to_comment_id');
            });
        }
    }
};
