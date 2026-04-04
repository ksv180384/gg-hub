<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guild_application_comments')) {
            return;
        }

        Schema::table('guild_application_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('guild_application_comments', 'hidden_reason')) {
                $table->text('hidden_reason')->nullable()->after('is_hidden');
            }
            if (! Schema::hasColumn('guild_application_comments', 'delete_reason')) {
                $table->text('delete_reason')->nullable()->after('hidden_reason');
            }
            if (! Schema::hasColumn('guild_application_comments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guild_application_comments')) {
            return;
        }

        Schema::table('guild_application_comments', function (Blueprint $table) {
            if (Schema::hasColumn('guild_application_comments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('guild_application_comments', 'delete_reason')) {
                $table->dropColumn('delete_reason');
            }
            if (Schema::hasColumn('guild_application_comments', 'hidden_reason')) {
                $table->dropColumn('hidden_reason');
            }
        });
    }
};
