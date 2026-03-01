<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Domains\Guild\Actions\GetGuildMemberUserIdsWithPermissionAction;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Support\Collection;

/**
 * Создаёт оповещения пользователям гильдии с правом «Подтверждение или отклонение заявок»:
 * приглашение такое-то было отозвано, ссылка на приглашение, кто отозвал.
 */
class CreateGuildInvitationRevokedNotificationAction
{
    private const PERMISSION_SLUG = 'podtverzdenie-ili-otklonenie-zaiavok';

    public function __construct(
        private GetGuildMemberUserIdsWithPermissionAction $getUserIdsWithPermissionAction
    ) {}

    /**
     * @return Collection<int, Notification>
     */
    public function __invoke(GuildApplication $application): Collection
    {
        $application->loadMissing(['guild', 'character', 'revokedByCharacter']);
        $guild = $application->guild;
        $invitedCharacterName = $application->character?->name ?? 'Персонаж';
        $revokerName = $application->revokedByCharacter?->name ?? 'Участник гильдии';

        $applicationLink = '/guilds/' . $guild->id . '/applications/list/' . $application->id;
        $message = "Приглашение «{$invitedCharacterName}» в гильдию «{$guild->name}» было отозвано. Отозвал(а): {$revokerName}.";

        $userIds = ($this->getUserIdsWithPermissionAction)($guild, self::PERMISSION_SLUG);

        $notifications = collect();
        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (!$user) {
                continue;
            }
            $notification = Notification::create([
                'user_id' => $userId,
                'message' => $message,
                'link' => $applicationLink,
            ]);
            $notifications->push($notification);
        }

        return $notifications;
    }
}
