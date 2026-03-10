<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;

/**
 * Проверяет право просмотра поста гильдии.
 *
 * Правила:
 * - опубликованный пост может смотреть любой участник гильдии;
 * - неопубликованный пост может смотреть только автор
 *   или участник гильдии с правом publikovat-post.
 */
final class CanViewGuildPostAction
{
    private const PERMISSION_PUBLISH_POST = 'publikovat-post';

    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}

    public function __invoke(User $user, Guild $guild, Post $post): bool
    {
        if ((int) $post->guild_id !== (int) $guild->id) {
            return false;
        }

        if ((int) $post->user_id === (int) $user->id) {
            return true;
        }

        $isMember = $guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if (! $isMember) {
            return false;
        }

        if ($post->status_guild === PostStatus::Published->value) {
            return true;
        }

        $userSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);

        return $userSlugs->contains(self::PERMISSION_PUBLISH_POST);
    }
}

