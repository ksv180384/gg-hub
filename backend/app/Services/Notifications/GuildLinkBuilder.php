<?php

namespace App\Services\Notifications;

use Domains\Guild\Models\Guild;
use Illuminate\Support\Carbon;

/**
 * Общий хелпер для построения публичного URL фронтенда с учётом субдомена игры.
 * Чтобы не дублировать одинаковую логику в каждом Action-уведомлении.
 */
class GuildLinkBuilder
{
    public function guildPath(Guild $guild): string
    {
        return '/guilds/' . $guild->id;
    }

    public function guildUrl(Guild $guild): string
    {
        $guild->loadMissing('game');
        return $this->baseUrlForGame($guild->game?->slug ?? null) . $this->guildPath($guild);
    }

    public function rosterUrl(Guild $guild): string
    {
        return $this->guildUrl($guild) . '/roster';
    }

    public function rosterMemberUrl(Guild $guild, int $characterId): string
    {
        return $this->guildUrl($guild) . '/roster/' . $characterId;
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
        return $this->guildUrl($guild) . $this->postPath($guild, $postId);
    }

    public function postPath(Guild $guild, int $postId): string
    {
        return $this->guildPath($guild) . '/posts/' . $postId;
    }

    public function eventUrl(Guild $guild, int $eventId): string
    {
        return $this->guildUrl($guild) . '/events/' . $eventId;
    }

    /**
     * Календарь гильдии; при переданном дне добавляет query `date=Y-m-d`
     * для выбора этого дня на фронте.
     */
    public function guildCalendarUrl(Guild $guild, ?Carbon $day = null): string
    {
        $path = $this->guildUrl($guild) . '/calendar';

        if ($day === null) {
            return $path;
        }

        return $path . '?date=' . $day->format('Y-m-d');
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
