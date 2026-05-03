<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Низкоуровневый клиент к notification-gg-hub: POST /api/discord.
 *
 * Сам по себе ничего не знает про гильдии и галочки — просто шлёт hub'у
 * URL Discord-вебхука и текст сообщения. Hub проксирует POST на этот URL.
 *
 * Конфиг берётся из канала `logging.channels.notification-hub`, чтобы
 * не дублировать env-переменные (URL/токен/timeout уже описаны там).
 */
class DiscordHubNotifier
{
    public function send(
        string $webhookUrl,
        ?string $content,
        array $embeds = [],
        ?string $username = null,
        ?string $avatarUrl = null,
    ): bool {
        $hubUrl = rtrim((string) config('logging.channels.notification-hub.url', ''), '/');
        $token = (string) config('logging.channels.notification-hub.token', '');
        $timeout = (int) (config('logging.channels.notification-hub.timeout') ?? 10);

        if ($hubUrl === '' || $token === '') {
            return false;
        }

        $webhookUrl = trim($webhookUrl);
        if ($webhookUrl === '') {
            return false;
        }

        $body = ['webhook_url' => $webhookUrl];
        if ($content !== null && $content !== '') {
            $body['content'] = $content;
        }
        if ($embeds !== []) {
            $body['embeds'] = $embeds;
        }
        if ($username !== null && $username !== '') {
            $body['username'] = $username;
        }
        if ($avatarUrl !== null && $avatarUrl !== '') {
            $body['avatar_url'] = $avatarUrl;
        }

        if (!isset($body['content']) && !isset($body['embeds'])) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'X-Notification-Hub-Token' => $token,
                'Accept' => 'application/json',
            ])
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($hubUrl . '/api/discord', $body);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Discord hub request failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        if (!$response->successful()) {
            Log::channel('single')->warning('Discord hub responded with non-2xx', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
