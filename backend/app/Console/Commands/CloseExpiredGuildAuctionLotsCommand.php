<?php

namespace App\Console\Commands;

use Domains\GuildAuction\Actions\CloseGuildAuctionLotAction;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloseExpiredGuildAuctionLotsCommand extends Command
{
    protected $signature = 'guild-auctions:close-expired';

    protected $description = 'Close expired guild auction lots and transfer winning items.';

    public function handle(CloseGuildAuctionLotAction $closeGuildAuctionLotAction): int
    {
        $closed = 0;
        $failed = 0;

        GuildAuctionLot::query()
            ->with('guild')
            ->where('status', GuildAuctionLot::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->chunkById(100, function ($lots) use ($closeGuildAuctionLotAction, &$closed, &$failed) {
                foreach ($lots as $lot) {
                    if (! (bool) ($lot->guild?->dkp_enabled ?? false)) {
                        continue;
                    }

                    try {
                        $closeGuildAuctionLotAction($lot->guild, $lot);
                        $closed++;
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error('Failed to close expired guild auction lot.', [
                            'guild_auction_lot_id' => $lot->id,
                            'guild_id' => $lot->guild_id,
                            'exception' => $e,
                        ]);
                    }
                }
            });

        $this->info("Closed {$closed} expired auction lot(s). Failed: {$failed}.");

        return self::SUCCESS;
    }
}
