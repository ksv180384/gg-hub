<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table) {
            if (! Schema::hasColumn('guilds', 'discord_notify_auction_lot_created')) {
                $table->boolean('discord_notify_auction_lot_created')->default(false)->after('discord_notify_post_published');
            }

            if (! Schema::hasColumn('guilds', 'discord_notify_auction_lot_closed')) {
                $table->boolean('discord_notify_auction_lot_closed')->default(false)->after('discord_notify_auction_lot_created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guilds', function (Blueprint $table) {
            $columns = [
                'discord_notify_auction_lot_closed',
                'discord_notify_auction_lot_created',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('guilds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
