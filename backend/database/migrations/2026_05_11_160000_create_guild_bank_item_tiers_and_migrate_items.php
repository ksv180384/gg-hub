<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_bank_item_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained('guilds')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 30)->nullable();
            $table->timestamps();

            $table->unique(['guild_id', 'name']);
            $table->index('guild_id');
        });

        Schema::table('guild_bank_items', function (Blueprint $table): void {
            $table->foreignId('guild_bank_item_tier_id')
                ->nullable()
                ->after('description')
                ->constrained('guild_bank_item_tiers')
                ->nullOnDelete();
        });

        $now = now();

        $distinctTiers = DB::table('guild_bank_items')
            ->select('guild_id', 'tier')
            ->whereNotNull('tier')
            ->where('tier', '!=', '')
            ->distinct()
            ->orderBy('guild_id')
            ->orderBy('tier')
            ->get();

        foreach ($distinctTiers as $row) {
            $tierId = DB::table('guild_bank_item_tiers')->insertGetId([
                'guild_id' => $row->guild_id,
                'name' => $row->tier,
                'color' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('guild_bank_items')
                ->where('guild_id', $row->guild_id)
                ->where('tier', $row->tier)
                ->update(['guild_bank_item_tier_id' => $tierId]);
        }

        Schema::table('guild_bank_items', function (Blueprint $table): void {
            $table->dropColumn(['tier', 'color']);
        });
    }

    public function down(): void
    {
        Schema::table('guild_bank_items', function (Blueprint $table): void {
            $table->string('tier', 50)->nullable()->after('description');
            $table->string('color', 30)->nullable()->after('tier');
        });

        $items = DB::table('guild_bank_items')
            ->leftJoin('guild_bank_item_tiers', 'guild_bank_items.guild_bank_item_tier_id', '=', 'guild_bank_item_tiers.id')
            ->select(
                'guild_bank_items.id',
                'guild_bank_item_tiers.name as tier_name',
                'guild_bank_item_tiers.color as tier_color',
            )
            ->get();

        foreach ($items as $item) {
            DB::table('guild_bank_items')
                ->where('id', $item->id)
                ->update([
                    'tier' => $item->tier_name,
                    'color' => $item->tier_color,
                ]);
        }

        Schema::table('guild_bank_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guild_bank_item_tier_id');
        });

        Schema::dropIfExists('guild_bank_item_tiers');
    }
};
