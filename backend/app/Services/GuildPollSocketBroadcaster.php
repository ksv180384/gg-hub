<?php

namespace App\Services;

use Domains\Poll\Models\Poll;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Отправка событий об изменениях голосований гильдии на socket_server по HTTP (best-effort).
 *
 * Socket-сервер ретранслирует события участникам в комнате `guild:{id}:polls`.
 * Сбои сокета не должны ломать основной HTTP-флоу — все ошибки логируем и глотаем.
 */
class GuildPollSocketBroadcaster
{
    private const DEFAULT_SOCKET_URL = 'http://socket-server-nodejs:3007';
    private const HTTP_TIMEOUT_SECONDS = 1.5;

    /**
     * Голосование создано/обновлено/закрыто/сброшено, либо изменились голоса —
     * клиенты подтянут актуальное состояние через backend.
     */
    public function broadcastChanged(Poll $poll): void
    {
        $this->broadcastChangedFor((int) $poll->guild_id, (int) $poll->id);
    }

    public function broadcastChangedFor(int $guildId, int $pollId): void
    {
        if ($guildId <= 0 || $pollId <= 0) {
            return;
        }

        $this->post('/guild-polls/broadcast-changed', [
            'guildId' => $guildId,
            'pollId' => $pollId,
        ]);
    }

    public function broadcastDeleted(int $guildId, int $pollId): void
    {
        if ($guildId <= 0 || $pollId <= 0) {
            return;
        }

        $this->post('/guild-polls/broadcast-deleted', [
            'guildId' => $guildId,
            'pollId' => $pollId,
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
            Log::debug('guild poll socket broadcast failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
