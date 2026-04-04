<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guild_application_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('guild_application_comments', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('guild_application_comments')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('guild_application_comments', 'replied_to_comment_id')) {
                $table->foreignId('replied_to_comment_id')
                    ->nullable()
                    ->after('parent_id')
                    ->constrained('guild_application_comments')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guild_application_comments', function (Blueprint $table) {
            if (Schema::hasColumn('guild_application_comments', 'replied_to_comment_id')) {
                $table->dropConstrainedForeignId('replied_to_comment_id');
            }
            if (Schema::hasColumn('guild_application_comments', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
        });
    }
};
