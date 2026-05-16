<?php

namespace App\Services\Notifications;

use Domains\Event\Models\Event;
use Illuminate\Support\Carbon;

/**
 * Форматирует моменты событий для Discord-оповещений в часовом поясе создателя.
 * В календаре время вводится как «локальное» (datetime-local); в БД хранится UTC.
 */
class EventNotificationTimeFormatter
{
    public function timezoneFor(Event $event): string
    {
        $event->loadMissing('creator.user');

        $timezone = $event->creator?->user?->timezone;

        if (! is_string($timezone) || trim($timezone) === '') {
            return 'UTC';
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            return 'UTC';
        }

        return $timezone;
    }

    public function toLocal(Carbon $instant, string $timezone): Carbon
    {
        return $instant->copy()->timezone($timezone);
    }
}
