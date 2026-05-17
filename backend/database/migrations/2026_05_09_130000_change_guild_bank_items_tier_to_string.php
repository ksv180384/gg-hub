<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_bank_items', function (Blueprint $table): void {
            // Было unsignedInteger default 1 → стало строкой (необязательно).
            $table->string('tier', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('guild_bank_items', function (Blueprint $table): void {
            $table->unsignedInteger('tier')->default(1)->change();
        });
    }
};

