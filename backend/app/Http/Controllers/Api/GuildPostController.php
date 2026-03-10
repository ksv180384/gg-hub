<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use App\Actions\Notification\CreatePostGuildPublishedNotificationAction;
use App\Actions\Notification\CreatePostGuildRejectedNotificationAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Actions\ListGuildPendingPostsForModerationAction;
use Domains\Post\Actions\ListGuildPostsForJournalAction;
use Domains\Post\Actions\PublishGuildPostAction;
use Domains\Post\Actions\RejectGuildPostAction;
use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class GuildPostController extends Controller
{
    public function __construct(
        private ListGuildPostsForJournalAction $listGuildPostsForJournalAction,
        private ListGuildPendingPostsForModerationAction $listGuildPendingPostsForModerationAction,
        private PublishGuildPostAction $publishGuildPostAction,
        private RejectGuildPostAction $rejectGuildPostAction,
        private CreatePostGuildPublishedNotificationAction $createPostGuildPublishedNotificationAction,
        private CreatePostGuildRejectedNotificationAction $createPostGuildRejectedNotificationAction,
    ) {}

    /**
     * Журнал гильдии: посты, которые относятся к гильдии и опубликованы в гильдии.
     * Query: per_page (опционально, 1–100). Если не передан — возвращается полный список.
     */
    public function index(Request $request, Guild $guild): AnonymousResourceCollection
    {
        $perPage = $request->query('per_page');
        $perPage = is_numeric($perPage) ? (int) $perPage : null;

        $posts = ($this->listGuildPostsForJournalAction)($guild, [
            'per_page' => $perPage,
        ]);

        $posts->loadMissing(['character', 'character.user', 'user']);

        return PostResource::collection($posts);
    }

    /**
     * Полная версия одного поста гильдии.
     */
    public function show(Guild $guild, Post $post): JsonResponse
    {
        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Посты гильдии, ожидающие публикации (модерация).
     * Доступно только участникам с правом publikovat-post.
     */
    public function pending(Request $request, Guild $guild): AnonymousResourceCollection
    {
        $perPage = $request->query('per_page');
        $perPage = is_numeric($perPage) ? (int) $perPage : null;

        $posts = ($this->listGuildPendingPostsForModerationAction)($guild, [
            'per_page' => $perPage,
        ]);

        $posts->loadMissing(['character', 'character.user', 'user']);

        return PostResource::collection($posts);
    }

    /**
     * Утвердить пост (опубликовать в гильдии).
     */
    public function publish(Request $request, Guild $guild, Post $post): JsonResponse
    {
        $post = ($this->publishGuildPostAction)($guild, $post);

        $this->createPostGuildPublishedNotificationAction->__invoke($guild, $post);

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Отклонить пост (не публиковать в гильдии).
     */
    public function reject(Request $request, Guild $guild, Post $post): JsonResponse
    {
        $post = ($this->rejectGuildPostAction)($guild, $post);

        $this->createPostGuildRejectedNotificationAction->__invoke($guild, $post);

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }
}

