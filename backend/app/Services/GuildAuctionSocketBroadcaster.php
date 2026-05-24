<?php

namespace App\Services;

use Domains\GuildAuction\Models\GuildAuctionLot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GuildAuctionSocketBroadcaster
{
    private const DEFAULT_SOCKET_URL = 'http://socket-server-nodejs:3007';
    private const HTTP_TIMEOUT_SECONDS = 1.5;

    public function broadcastChanged(GuildAuctionLot $lot): void
    {
        $this->broadcastChangedFor((int) $lot->guild_id, (int) $lot->id);
    }

    public function broadcastChangedFor(int $guildId, int $lotId): void
    {
        if ($guildId <= 0 || $lotId <= 0) {
            return;
        }

        $this->post('/guild-auctions/broadcast-changed', [
            'guildId' => $guildId,
            'lotId' => $lotId,
        ]);
    }

    private function post(string $path, array $payload): void
    {
        $base = rtrim((string) env('SOCKET_SERVER_URL', self::DEFAULT_SOCKET_URL), '/');

        try {
            Http::timeout(self::HTTP_TIMEOUT_SECONDS)->post($base . $path, $payload);
        } catch (Throwable $e) {
            Log::debug('guild auction socket broadcast failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
