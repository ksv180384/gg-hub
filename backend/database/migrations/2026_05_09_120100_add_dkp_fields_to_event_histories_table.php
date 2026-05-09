<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_histories', function (Blueprint $table): void {
            $table->integer('dkp_base_points')
                ->nullable()
                ->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_histories', function (Blueprint $table): void {
            $table->dropColumn('dkp_base_points');
        });
    }
};

