<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_histories', function (Blueprint $table) {
            $table->boolean('distribute_dkp_to_participants')
                ->default(false)
                ->after('dkp_base_points');
        });
    }

    public function down(): void
    {
        Schema::table('event_histories', function (Blueprint $table) {
            $table->dropColumn('distribute_dkp_to_participants');
        });
    }
};
