<?php

namespace App\Services\Notifications;

use Domains\Guild\Models\Guild;

/**
 * Общий хелпер для построения публичного URL фронтенда с учётом субдомена игры.
 * Чтобы не дублировать одинаковую логику в каждом Action-уведомлении.
 */
class GuildLinkBuilder
{
    public function guildUrl(Guild $guild): string
    {
        $guild->loadMissing('game');
        return $this->baseUrlForGame($guild->game?->slug ?? null) . '/guilds/' . $guild->id;
    }

    public function rosterUrl(Guild $guild): string
    {
        return $this->guildUrl($guild) . '/roster';
    }

    public function applicationUrl(Guild $guild, int $applicationId): string
    {
        return $this->guildUrl($guild) . '/applications/' . $applicationId;
    }

    public function pollsUrl(Guild $guild): string
    {
        return $this->guildUrl($guild) . '/polls';
    }

    public function postUrl(Guild $guild, int $postId): string
    {
        return $this->guildUrl($guild) . '/posts/' . $postId;
    }

    public function eventUrl(Guild $guild, int $eventId): string
    {
        return $this->guildUrl($guild) . '/events/' . $eventId;
    }

    private function baseUrlForGame(?string $gameSlug): string
    {
        $raw = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $parsed = parse_url($raw) ?: [];
        $scheme = ($parsed['scheme'] ?? 'http') . '://';
        $host = $parsed['host'] ?? 'localhost';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        if ($gameSlug) {
            $host = $gameSlug . '.' . $host;
        }

        return $scheme . $host . $port;
    }
}
