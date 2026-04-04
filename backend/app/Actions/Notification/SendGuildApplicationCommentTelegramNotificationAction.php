<?php

namespace App\Actions\Notification;

use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildApplicationComment;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет уведомление в Telegram о новом комментарии к заявке в гильдию.
 * Выполняется после отправки HTTP-ответа, чтобы не замедлять запрос.
 */
class SendGuildApplicationCommentTelegramNotificationAction
{
    public function commentCreated(GuildApplication $application, GuildApplicationComment $comment): void
    {
        $url = $this->buildApplicationUrl($application);
        $author = $comment->character?->name ?? $comment->user?->name ?? 'Пользователь';
        $guildName = $application->guild?->name ?? 'Гильдия';
        $message = "Новый комментарий к заявке #{$application->id} в гильдию «{$guildName}» от {$author}: {$url}";
        dispatch(fn () => Log::channel('telegram')->info($message))->afterResponse();
    }

    private function buildApplicationUrl(GuildApplication $application): string
    {
        $application->loadMissing(['guild.game']);
        $guild = $application->guild;
        $base = $this->baseUrlWithGameSubdomain($guild?->game?->slug ?? null);

        return $base . '/guilds/' . $application->guild_id . '/applications/' . $application->id;
    }

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
