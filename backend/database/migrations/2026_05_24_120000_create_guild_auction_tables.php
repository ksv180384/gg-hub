<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_auction_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->foreignId('guild_bank_item_id')->constrained('guild_bank_items')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guild_bank_item_grant_id')->nullable()->constrained('guild_bank_item_grants')->nullOnDelete();
            $table->unsignedInteger('start_price')->default(0);
            $table->unsignedInteger('current_bid_amount')->nullable();
            $table->foreignId('current_bid_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('active');
            $table->dateTime('ends_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['guild_id', 'status', 'ends_at']);
            $table->index(['guild_id', 'guild_bank_item_id', 'status'], 'gal_guild_item_status_idx');
        });

        Schema::create('guild_auction_bids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_auction_lot_id')->constrained('guild_auction_lots')->cascadeOnDelete();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index(['guild_id', 'created_at']);
            $table->index(['guild_auction_lot_id', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_auction_bids');
        Schema::dropIfExists('guild_auction_lots');
    }
};
