<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationSocketBroadcaster;

/**
 * Массово удаляет оповещения пользователя по списку идентификаторов
 * (чужие игнорируются). После удаления один раз пушит в socket_server,
 * чтобы избежать N HTTP-запросов (observer получает событие `deleting`
 * ДО удаления и на `deleted` не сработает для удалённых одним запросом).
 *
 * Возвращает массив реально удалённых id.
 */
class BulkDeleteNotificationsAction
{
    public function __construct(
        private readonly NotificationSocketBroadcaster $broadcaster
    ) {}

    /**
     * @param  int[]  $ids
     * @return int[]
     */
    public function __invoke(User $user, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $deletableIds = Notification::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($deletableIds === []) {
            return [];
        }

        // Массовое удаление минует модельные события Eloquent, поэтому observer
        // не запустится — транслируем удаление в socket вручную одним запросом.
        Notification::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $deletableIds)
            ->delete();

        $this->broadcaster->broadcastDeleted($user->id, $deletableIds);

        return $deletableIds;
    }
}
