<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Domains\Guild\Actions\GetGuildMemberUserIdsWithPermissionAction;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Support\Collection;

/**
 * Создаёт оповещения пользователям гильдии с правом «Подтверждение или отклонение заявок»:
 * что указанный пользователь (персонаж) подал заявку в гильдию, со ссылкой на заявку.
 */
class CreateGuildApplicationNotificationAction
{
    private const PERMISSION_SLUG = 'podtverzdenie-ili-otklonenie-zaiavok';

    public function __construct(
        private GetGuildMemberUserIdsWithPermissionAction $getUserIdsWithPermissionAction
    ) {}

    /**
     * @return Collection<int, Notification>
     */
    public function __invoke(GuildApplication $application, string $applicationLink): Collection
    {
        $application->loadMissing(['guild', 'character']);
        $guild = $application->guild;
        $characterName = $application->character?->name ?? 'Пользователь';

        $userIds = ($this->getUserIdsWithPermissionAction)($guild, self::PERMISSION_SLUG);

        $notifications = collect();
        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (!$user) {
                continue;
            }
            $notification = Notification::create([
                'user_id' => $userId,
                'message' => "{$characterName} подал(а) заявку в гильдию «{$guild->name}».",
                'link' => $applicationLink,
            ]);
            $notifications->push($notification);
        }

        return $notifications;
    }
}
