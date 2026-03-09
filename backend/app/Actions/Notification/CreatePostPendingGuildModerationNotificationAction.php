<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;
use Domains\Guild\Actions\GetGuildMemberUserIdsWithPermissionAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Models\Post;
use Illuminate\Support\Collection;

/**
 * Создаёт оповещения участникам гильдии с правом «Публиковать пост» (publikovat-post):
 * пост отправлен на модерацию и требует одобрения перед показом в гильдии.
 */
class CreatePostPendingGuildModerationNotificationAction
{
    private const PERMISSION_SLUG = 'publikovat-post';

    public function __construct(
        private GetGuildMemberUserIdsWithPermissionAction $getUserIdsWithPermissionAction
    ) {}

    /**
     * @return Collection<int, Notification>
     */
    public function __invoke(Guild $guild, Post $post, string $postsModerationLink): Collection
    {
        $post->loadMissing(['user', 'character']);
        $authorName = $post->character?->name ?? $post->user?->name ?? 'Пользователь';

        $userIds = ($this->getUserIdsWithPermissionAction)($guild, self::PERMISSION_SLUG);

        $notifications = collect();
        foreach ($userIds as $userId) {
            if ((int) $userId === (int) $post->user_id) {
                continue;
            }
            $user = User::query()->find($userId);
            if (!$user) {
                continue;
            }
            $notification = Notification::create([
                'user_id' => $userId,
                'message' => "Пост «{$post->title}» от {$authorName} в гильдии «{$guild->name}» ожидает модерации.",
                'link' => $postsModerationLink,
            ]);
            $notifications->push($notification);
        }

        return $notifications;
    }
}
