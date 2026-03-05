<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_histories', function (Blueprint $table) {
            if (Schema::hasColumn('event_histories', 'title')) {
                $table->dropColumn('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('event_histories', 'title')) {
                $table->string('title')->after('event_history_title_id');
            }
        });
    }
};

