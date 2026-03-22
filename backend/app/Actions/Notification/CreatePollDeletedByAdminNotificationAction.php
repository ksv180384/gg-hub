<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Poll\Models\Poll;

/**
 * Оповещение автору голосования: голосование было удалено администратором.
 */
class CreatePollDeletedByAdminNotificationAction
{
    public function __invoke(Poll $poll, string $reason): ?Notification
    {
        $poll->loadMissing(['creator', 'guild']);

        $userId = $poll->created_by;
        if (! $userId) {
            return null;
        }

        $title = $poll->title ?: 'Без названия';
        $guildName = $poll->guild?->name ?? 'гильдии';

        $message = "Ваше голосование «{$title}» в гильдии «{$guildName}» было удалено администратором.";
        if ($reason !== '') {
            $message .= ' Причина: ' . $reason;
        }

        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => '',
        ]);
    }
}
