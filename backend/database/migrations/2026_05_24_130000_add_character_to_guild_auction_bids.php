<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_auction_lots', function (Blueprint $table): void {
            $table->foreignId('current_bid_character_id')
                ->nullable()
                ->after('current_bid_user_id')
                ->constrained('characters')
                ->nullOnDelete();
        });

        Schema::table('guild_auction_bids', function (Blueprint $table): void {
            $table->foreignId('character_id')
                ->nullable()
                ->after('user_id')
                ->constrained('characters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guild_auction_bids', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('character_id');
        });

        Schema::table('guild_auction_lots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_bid_character_id');
        });
    }
};
