<?php

namespace App\Actions\Notification;

use App\Services\Notifications\DiscordHubNotifier;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет оповещение о событии гильдии в Discord через notification-gg-hub.
 *
 * Перед отправкой проверяет:
 *  1. У гильдии настроен URL Discord-вебхука (`discord_webhook_url`).
 *  2. Соответствующая галочка типа оповещения включена (`discord_notify_*`).
 *
 * Сам HTTP-запрос выполняется в `dispatch(...)->afterResponse()`,
 * чтобы не подтормаживать ответ контроллера, инициировавшего событие.
 */
class SendGuildDiscordNotificationAction
{
    /**
     * Допустимые ключи галочек гильдии (поля в `guilds`).
     */
    private const ALLOWED_KEYS = [
        'discord_notify_application_new',
        'discord_notify_member_joined',
        'discord_notify_member_left',
        'discord_notify_event_starting',
        'discord_notify_poll_started',
        'discord_notify_role_changed',
        'discord_notify_post_published',
    ];

    public function __construct(
        private DiscordHubNotifier $notifier,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $embeds Discord embeds (опционально, max 10)
     */
    public function __invoke(
        Guild $guild,
        string $notificationKey,
        ?string $content,
        array $embeds = [],
    ): void {
        if (!in_array($notificationKey, self::ALLOWED_KEYS, true)) {
            Log::channel('single')->warning('Unknown Discord notification key', [
                'guild_id' => $guild->id,
                'notification_key' => $notificationKey,
            ]);

            return;
        }

        $webhookUrl = (string) ($guild->discord_webhook_url ?? '');
        if (trim($webhookUrl) === '') {
            return;
        }

        if (!$guild->{$notificationKey}) {
            return;
        }

        $notifier = $this->notifier;
        dispatch(static function () use ($notifier, $webhookUrl, $content, $embeds): void {
            $notifier->send($webhookUrl, $content, $embeds);
        })->afterResponse();
    }
}
