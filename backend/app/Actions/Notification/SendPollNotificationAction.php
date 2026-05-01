<?php

namespace App\Actions\Notification;

use App\Services\Notifications\GuildLinkBuilder;
use Domains\Poll\Models\Poll;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет уведомление о создании голосования через notification-gg-hub.
 * Выполняется после отправки HTTP-ответа, чтобы не замедлять запрос.
 *
 * Параллельно (если у гильдии настроен Discord-вебхук и галочка
 * `discord_notify_poll_started` включена) шлёт оповещение в Discord.
 */
class SendPollNotificationAction
{
    public function __construct(
        private SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function pollCreated(Poll $poll): void
    {
        $poll->loadMissing(['guild.game']);
        $guild = $poll->guild;
        $url = $guild ? $this->linkBuilder->pollsUrl($guild) : '';
        $title = (string) $poll->title;

        $message = 'Создано голосование: ' . $title . ' — ' . $url;
        $channel = (string) config('logging.notifications_channel', 'notification-hub');
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();

        if ($guild) {
            $discordMessage = "Запущено новое голосование: «{$title}»\n{$url}";
            ($this->sendGuildDiscordNotificationAction)(
                $guild,
                'discord_notify_poll_started',
                $discordMessage,
            );
        }
    }
}
