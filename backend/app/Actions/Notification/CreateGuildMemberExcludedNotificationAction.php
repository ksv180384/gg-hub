<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

/**
 * Создаёт оповещения при исключении участника из гильдии:
 * — всем участникам гильдии (кроме исключившего): «Гильдия «Z»: персонаж X был исключён. Исключил: Y.»;
 * — исключённому пользователю: «Гильдия «Z»: вы были исключены. Исключил: Y.».
 */
class CreateGuildMemberExcludedNotificationAction
{
    private const ROSTER_LINK_TEMPLATE = '/guilds/%d/roster';

    /**
     * @param  array<int, int>  $recipientUserIds  ID пользователей, которым отправить уведомление (все участники кроме исключившего)
     */
    public function __invoke(
        Guild $guild,
        string $excludedCharacterName,
        int $excludedUserId,
        array $recipientUserIds,
        string $excluderCharacterName
    ): Collection {
        $guildName = $guild->name;
        $link = sprintf(self::ROSTER_LINK_TEMPLATE, $guild->id);

        $messageToOthers = "Гильдия «{$guildName}»: персонаж {$excludedCharacterName} был исключён. Исключил: {$excluderCharacterName}.";
        $messageToExcluded = "Гильдия «{$guildName}»: вы были исключены. Исключил: {$excluderCharacterName}.";

        $notifications = collect();
        foreach ($recipientUserIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }
            $message = (int) $userId === (int) $excludedUserId
                ? $messageToExcluded
                : $messageToOthers;

            $notification = Notification::create([
                'user_id' => $userId,
                'message' => $message,
                'link' => $link,
            ]);
            $notifications->push($notification);
        }

        return $notifications;
    }
}
