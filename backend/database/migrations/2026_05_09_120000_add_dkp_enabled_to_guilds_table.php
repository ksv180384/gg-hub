<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table): void {
            $table->boolean('dkp_enabled')
                ->default(false)
                ->after('is_recruiting');
        });
    }

    public function down(): void
    {
        Schema::table('guilds', function (Blueprint $table): void {
            $table->dropColumn('dkp_enabled');
        });
    }
};

