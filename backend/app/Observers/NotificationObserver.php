<?php

namespace App\Observers;

use App\Models\Notification;
use App\Services\NotificationSocketBroadcaster;

/**
 * Автоматически транслирует изменения уведомлений в socket_server,
 * чтобы действиям не приходилось вызывать брадкастер руками.
 */
class NotificationObserver
{
    public function __construct(
        private readonly NotificationSocketBroadcaster $broadcaster
    ) {}

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
