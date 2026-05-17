<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_members', function (Blueprint $table) {
            $table->decimal('dkp_coefficient', 6, 2)
                ->default(1)
                ->after('guild_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('guild_members', function (Blueprint $table) {
            $table->dropColumn('dkp_coefficient');
        });
    }
};
