<?php

namespace Domains\Post\Actions;

use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Посты гильдии, ожидающие модерации (для пользователей с правом publikovat-post):
 * - относятся к гильдии (guild_id = id гильдии);
 * - видимы в гильдии (is_visible_guild = true);
 * - статус в гильдии pending;
 * - отсортированы по дате создания (от новых к старым).
 *
 * @param  array{per_page?: int|null}  $params
 */
final class ListGuildPendingPostsForModerationAction
{
    public function __invoke(Guild $guild, array $params = []): Collection|LengthAwarePaginator
    {
        $query = Post::query()
            ->withCount(['postComments as comments_count'])
            ->where('guild_id', $guild->id)
            ->where('is_visible_guild', true)
            ->where('status_guild', PostStatus::Pending->value)
            ->orderByDesc('created_at');

        $perPage = $params['per_page'] ?? null;
        if ($perPage !== null) {
            $perPage = (int) $perPage;
            if ($perPage < 1) {
                $perPage = 15;
            }
            if ($perPage > 100) {
                $perPage = 100;
            }

            return $query->paginate($perPage);
        }

        return $query->get();
    }
}

