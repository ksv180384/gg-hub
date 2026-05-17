<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_history_participants', function (Blueprint $table): void {
            $table->decimal('dkp_coefficient', 6, 2)
                ->default(1)
                ->after('external_name');
            $table->integer('dkp_points_override')
                ->nullable()
                ->after('dkp_coefficient');
        });
    }

    public function down(): void
    {
        Schema::table('event_history_participants', function (Blueprint $table): void {
            $table->dropColumn(['dkp_coefficient', 'dkp_points_override']);
        });
    }
};

