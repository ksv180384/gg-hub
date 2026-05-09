<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_bank_item_grants', function (Blueprint $table): void {
            $table->dateTime('revoked_at')->nullable()->after('granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('guild_bank_item_grants', function (Blueprint $table): void {
            $table->dropColumn('revoked_at');
        });
    }
};
