<?php

namespace App\Actions\Notification;

use Domains\Guild\Models\GuildApplication;
use Domains\Notification\Models\Notification;

/**
 * Создаёт оповещение пользователю (владельцу персонажа): его пригласили в гильдию.
 */
class CreateGuildInvitationNotificationAction
{
    public function __invoke(GuildApplication $application): ?Notification
    {
        $application->loadMissing(['guild', 'character', 'invitedByCharacter']);
        $guild = $application->guild;
        $character = $application->character;
        $inviterCharacter = $application->invitedByCharacter;
        if (! $character) {
            return null;
        }

        $userId = $character->user_id;
        if (! $userId) {
            return null;
        }

        $inviterName = $inviterCharacter?->name ?? 'Участник гильдии';
        $message = "Вас пригласили в гильдию «{$guild->name}». Приглашение отправил(а): {$inviterName}.";
        $link = '/guilds/'.$guild->id.'/applications/my/'.$application->id;

        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link,
        ]);
    }
}
