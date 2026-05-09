<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Guild\Models\GuildApplication;

/**
 * Создаёт оповещение пользователю (владельцу персонажа): приглашение в гильдию было отозвано.
 */
class CreateGuildInvitationRevokedForUserNotificationAction
{
    public function __invoke(GuildApplication $application): ?Notification
    {
        $application->loadMissing(['guild', 'character', 'revokedByCharacter']);

        $character = $application->character;
        if (!$character) {
            return null;
        }

        $userId = $character->user_id;
        if (!$userId) {
            return null;
        }

        $guild = $application->guild;
        $revokerName = $application->revokedByCharacter?->name ?? 'Участник гильдии';
        $message = "Приглашение в гильдию «{$guild->name}» было отозвано. Отозвал(а): {$revokerName}.";
        $link = '/guilds/' . $guild->id . '/applications/my/' . $application->id;

        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link,
        ]);
    }
}

