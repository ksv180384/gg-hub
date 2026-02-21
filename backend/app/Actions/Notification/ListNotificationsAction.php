<?php

namespace App\Actions\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListNotificationsAction
{
    private const PER_PAGE = 20;

    /**
     * @return array{paginator: LengthAwarePaginator, unread_count: int}
     */
    public function __invoke(User $user, int $perPage = self::PER_PAGE): array
    {
        $paginator = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $unreadCount = $user->notifications()->whereNull('read_at')->count();

        return [
            'paginator' => $paginator,
            'unread_count' => $unreadCount,
        ];
    }
}
