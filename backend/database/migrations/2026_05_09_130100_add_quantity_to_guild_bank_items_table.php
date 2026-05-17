<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_bank_items', function (Blueprint $table): void {
            // Nullable: если null — количество не ограничено.
            $table->unsignedInteger('quantity')
                ->nullable()
                ->after('dkp_cost');
        });
    }

    public function down(): void
    {
        Schema::table('guild_bank_items', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};

