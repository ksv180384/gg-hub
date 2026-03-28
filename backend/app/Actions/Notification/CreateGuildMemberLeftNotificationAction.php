<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Domains\Guild\Actions\GetGuildMemberUserIdsWithPermissionAction;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

/**
 * Создаёт оповещения пользователям гильдии с правом «Подтверждение или отклонение заявок»:
 * участник покинул гильдию.
 */
class CreateGuildMemberLeftNotificationAction
{
    private const PERMISSION_SLUG = 'podtverzdenie-ili-otklonenie-zaiavok';

    public function __construct(
        private GetGuildMemberUserIdsWithPermissionAction $getUserIdsWithPermissionAction
    ) {}

    /**
     * @return Collection<int, Notification>
     */
    public function __invoke(Guild $guild, string $leftCharacterName): Collection
    {
        $guild->loadMissing(['leader']);

        $link = '/guilds/' . $guild->id . '/roster';
        $message = "Участник {$leftCharacterName} покинул(а) гильдию «{$guild->name}».";

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
                'link' => $link,
            ]);
            $notifications->push($notification);
        }

        return $notifications;
    }
}
