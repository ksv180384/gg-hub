<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_auction_lots', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1)->after('guild_bank_item_id');
        });

        Schema::table('guild_bank_item_grants', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1)->after('granted_by_character_id');
        });
    }

    public function down(): void
    {
        Schema::table('guild_auction_lots', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });

        Schema::table('guild_bank_item_grants', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
