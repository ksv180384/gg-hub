<?php

namespace App\Actions\Notification;

use Domains\Poll\Models\Poll;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет уведомление о создании голосования через notification-gg-hub.
 * Выполняется после отправки HTTP-ответа, чтобы не замедлять запрос.
 */
class SendPollNotificationAction
{
    public function pollCreated(Poll $poll): void
    {
        $url = $this->buildPollUrl($poll);
        $message = 'Создано голосование: ' . $poll->title . ' — ' . $url;
        $channel = (string) config('logging.notifications_channel', 'notification-hub');
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    private function buildPollUrl(Poll $poll): string
    {
        $poll->loadMissing(['guild.game']);
        $guild = $poll->guild;
        $base = $this->baseUrlWithGameSubdomain($guild?->game?->slug ?? null);

        return $base . '/guilds/' . $poll->guild_id . '/polls';
    }

    /**
     * Базовый URL фронтенда с учётом субдомена игры.
     */
    private function baseUrlWithGameSubdomain(?string $gameSlug): string
    {
        $raw = rtrim(config('app.frontend_url', config('app.url')), '/');
        $parsed = parse_url($raw);
        $scheme = ($parsed['scheme'] ?? 'http') . '://';
        $host = $parsed['host'] ?? 'localhost';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        if ($gameSlug) {
            $host = $gameSlug . '.' . $host;
        }

        return $scheme . $host . $port;
    }
}
