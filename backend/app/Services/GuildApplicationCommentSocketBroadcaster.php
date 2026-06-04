<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GuildApplicationCommentSocketBroadcaster
{
    private const DEFAULT_SOCKET_URL = 'http://socket-server-nodejs:3007';
    private const HTTP_TIMEOUT_SECONDS = 1.5;

    public function broadcastChangedFor(int $guildId, int $applicationId, int $commentId, string $action): void
    {
        if ($guildId <= 0 || $applicationId <= 0 || $commentId <= 0) {
            return;
        }

        $this->post('/guild-application-comments/broadcast-changed', [
            'guildId' => $guildId,
            'applicationId' => $applicationId,
            'commentId' => $commentId,
            'action' => $action,
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
            Log::debug('guild application comment socket broadcast failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
