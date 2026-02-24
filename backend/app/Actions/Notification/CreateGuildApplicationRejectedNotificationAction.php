<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Guild\Models\GuildApplication;

/**
 * Создаёт оповещение пользователю, подавшему заявку в гильдию: заявка была отклонена.
 */
class CreateGuildApplicationRejectedNotificationAction
{
    public function __invoke(GuildApplication $application): ?Notification
    {
        $application->loadMissing(['guild', 'character']);
        $guild = $application->guild;
        $character = $application->character;
        if (!$character) {
            return null;
        }

        $userId = $character->user_id;
        if (!$userId) {
            return null;
        }

        return Notification::create([
            'user_id' => $userId,
            'message' => "Ваша заявка в гильдию «{$guild->name}» была отклонена.",
            // Страница просмотра заявки пользователем, подавшим её
            'link' => '/guilds/' . $guild->id . '/applications/my/' . $application->id,
        ]);
    }
}
