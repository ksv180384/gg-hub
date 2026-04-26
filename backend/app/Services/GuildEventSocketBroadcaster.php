<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Отправка событий об изменениях календарных событий гильдии на socket_server по HTTP (best-effort).
 *
 * Socket-сервер ретранслирует события участникам в комнате `guild:{id}:events`.
 * Сбои сокета не должны ломать основной HTTP-флоу — все ошибки логируем и глотаем.
 */
class GuildEventSocketBroadcaster
{
    private const DEFAULT_SOCKET_URL = 'http://socket-server-nodejs:3007';
    private const HTTP_TIMEOUT_SECONDS = 1.5;

    public function broadcastChangedFor(int $guildId, int $eventId): void
    {
        if ($guildId <= 0 || $eventId <= 0) {
            return;
        }

        $this->post('/guild-events/broadcast-changed', [
            'guildId' => $guildId,
            'eventId' => $eventId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): void
    {
        $base = rtrim((string) env('SOCKET_SERVER_URL', self::DEFAULT_SOCKET_URL), '/');

        try {
            Http::timeout(self::HTTP_TIMEOUT_SECONDS)->post($base . $path, $payload);
        } catch (Throwable $e) {
            Log::debug('guild event socket broadcast failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

