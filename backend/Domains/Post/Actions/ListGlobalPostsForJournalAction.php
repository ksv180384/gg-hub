<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Общие посты для журнала (раздел «Общие»):
 * - относятся к игре (game_id = id игры);
 * - опубликованы в общем разделе (is_visible_global = true, status_global = published);
 * - отсортированы по дате публикации по убыванию.
 *
 * @param  array{per_page?: int|null}  $params
 */
final class ListGlobalPostsForJournalAction
{
    public function __invoke(int $gameId, array $params = []): Collection|LengthAwarePaginator
    {
        $query = Post::query()
            ->withCount(['postComments as comments_count'])
            ->where('game_id', $gameId)
            ->where('is_visible_global', true)
            ->where('status_global', PostStatus::Published->value)
            ->whereNotNull('published_at_global')
            ->orderByDesc('published_at_global')
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
