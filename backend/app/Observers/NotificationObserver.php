<?php

namespace App\Observers;

use App\Services\NotificationSocketBroadcaster;
use Domains\Guild\Models\Guild;
use Domains\Notification\Models\Notification;
use Domains\Post\Models\Post;

/**
 * Автоматически транслирует изменения уведомлений в socket_server,
 * чтобы действиям не приходилось вызывать брадкастер руками.
 */
class NotificationObserver
{
    public function __construct(
        private readonly NotificationSocketBroadcaster $broadcaster
    ) {}

    /**
     * Лента in-app уведомлений глобальна, поэтому каждое уведомление со ссылкой
     * на конкретную гильдию сохраняет структурированный контекст игры и гильдии.
     */
    public function creating(Notification $notification): void
    {
        $link = (string) $notification->link;
        $guild = null;

        if (preg_match('~(?:^|/)guilds/(\d+)(?:/|$)~', $link, $matches)) {
            $guild = Guild::query()
                ->withTrashed()
                ->with('game:id,name')
                ->find((int) $matches[1]);
        } elseif (preg_match('~(?:^|/)my-posts/(\d+)(?:/|$)~', $link, $matches)) {
            $post = Post::query()
                ->with('guild.game:id,name')
                ->find((int) $matches[1]);
            $guild = $post?->guild;
        }

        if (! $guild || ! $guild->game) {
            return;
        }

        $notification->game_id = $guild->game_id;
        $notification->guild_id = $guild->id;
        $notification->setRelation('game', $guild->game);
        $notification->setRelation('guild', $guild);
    }

    public function created(Notification $notification): void
    {
        $this->broadcaster->broadcastCreated($notification);
    }

    public function updated(Notification $notification): void
    {
        if (! $notification->wasChanged('read_at')) {
            return;
        }
        if ($notification->read_at === null) {
            return;
        }
        $this->broadcaster->broadcastRead($notification);
    }

    public function deleted(Notification $notification): void
    {
        $this->broadcaster->broadcastDeleted($notification->user_id, [$notification->id]);
    }
}
