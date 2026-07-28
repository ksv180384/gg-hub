<?php

namespace App\Actions\Notification;

use Illuminate\Support\Facades\Log;

class SendAdminTelegramNotificationAction
{
    public function __invoke(string $message): void
    {
        $channel = (string) config('logging.notifications_channel', 'notification-hub');

        dispatch(
            static fn () => Log::channel($channel)->info($message),
        )->afterResponse();
    }
}
