<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MarkNotificationReadAction
{
    public function __invoke(User $user, Notification $notification): Notification
    {
        if ($notification->user_id !== $user->id) {
            throw new HttpException(403, 'Forbidden');
        }
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return $notification->fresh();
    }
}
