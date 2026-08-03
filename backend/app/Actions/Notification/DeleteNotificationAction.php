<?php

namespace App\Actions\Notification;

use Domains\Notification\Models\Notification;
use Domains\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteNotificationAction
{
    public function __invoke(User $user, Notification $notification): void
    {
        if ($notification->user_id !== $user->id) {
            throw new HttpException(403, 'Forbidden');
        }
        $notification->delete();
    }
}
