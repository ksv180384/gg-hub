<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_history_titles', function (Blueprint $table) {
            $table->unsignedInteger('dkp_base_points')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('event_history_titles', function (Blueprint $table) {
            $table->dropColumn('dkp_base_points');
        });
    }
};
