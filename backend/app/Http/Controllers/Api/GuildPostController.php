<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostListResource;
use App\Http\Resources\Post\PostResource;
use App\Actions\Notification\CreatePostGuildPublishedNotificationAction;
use App\Actions\Notification\CreatePostGuildRejectedNotificationAction;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Actions\ListGuildPendingPostsForModerationAction;
use Domains\Post\Actions\ListGuildPostsForJournalAction;
use Domains\Post\Actions\CanViewGuildPostAction;
use Domains\Post\Actions\BlockGuildPostAction;
use Domains\Post\Actions\PublishGuildPostAction;
use Domains\Post\Actions\UnblockGuildPostAction;
use Domains\Post\Actions\RecordPostViewAction;
use Domains\Post\Actions\RejectGuildPostAction;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class GuildPostController extends Controller
{
    public function __construct(
        private ListGuildPostsForJournalAction $listGuildPostsForJournalAction,
        private ListGuildPendingPostsForModerationAction $listGuildPendingPostsForModerationAction,
        private CanViewGuildPostAction $canViewGuildPostAction,
        private RecordPostViewAction $recordPostViewAction,
        private PublishGuildPostAction $publishGuildPostAction,
        private RejectGuildPostAction $rejectGuildPostAction,
        private BlockGuildPostAction $blockGuildPostAction,
        private UnblockGuildPostAction $unblockGuildPostAction,
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction,
        private CreatePostGuildPublishedNotificationAction $createPostGuildPublishedNotificationAction,
        private CreatePostGuildRejectedNotificationAction $createPostGuildRejectedNotificationAction,
    ) {}

    /**
     * Журнал гильдии: посты, которые относятся к гильдии и опубликованы в гильдии.
     * Query: per_page (опционально, 1–100); filter=blocked — заблокированные (только при праве publikovat-post).
     */
    public function index(Request $request, Guild $guild): AnonymousResourceCollection
    {
        $perPage = $request->query('per_page');
        $perPage = is_numeric($perPage) ? (int) $perPage : null;

        $filter = $request->query('filter');
        $filterBlocked = $filter === 'blocked';
        if ($filterBlocked) {
            $userSlugs = ($this->getUserGuildPermissionSlugsAction)($request->user(), $guild);
            if (! $userSlugs->contains('publikovat-post')) {
                $filterBlocked = false;
            }
        }

        $params = ['per_page' => $perPage];
        if ($filterBlocked) {
            $params['filter'] = 'blocked';
        }

        $posts = ($this->listGuildPostsForJournalAction)($guild, $params);

        $posts->loadMissing(['character', 'character.user', 'user']);

        return PostListResource::collection($posts);
    }

    /**
     * Полная версия одного поста гильдии.
     */
    public function show(Request $request, Guild $guild, Post $post): JsonResponse
    {
        if (! ($this->canViewGuildPostAction)($request->user(), $guild, $post)) {
            abort(404);
        }

        $sessionId = $request->session()?->getId() ?? '';
        ($this->recordPostViewAction)($post, $request->user(), $sessionId);

        $post->loadMissing(['character', 'character.user', 'user']);
        $post->refresh();
        $post->loadCount(['postComments as comments_count']);

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

        return PostListResource::collection($posts);
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

    /**
     * Заблокировать пост только для гильдии (скрыть из гильдейского журнала).
     * Доступно участникам с правом publikovat-post.
     */
    public function block(Request $request, Guild $guild, Post $post): JsonResponse
    {
        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        $post = ($this->blockGuildPostAction)($post);

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Разблокировать пост для гильдии (status_guild → hidden).
     * Нельзя разблокировать, если пост заблокирован в общем журнале.
     */
    public function unblock(Request $request, Guild $guild, Post $post): JsonResponse
    {
        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        if ($post->status_global === PostStatus::Blocked->value) {
            abort(403, 'Нельзя разблокировать пост в гильдии: он заблокирован в общем журнале.');
        }

        $post = ($this->unblockGuildPostAction)($post);

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Засчитать просмотр поста (например, при воспроизведении видео в превью).
     */
    public function recordView(Request $request, Guild $guild, Post $post): JsonResponse
    {
        if (! ($this->canViewGuildPostAction)($request->user(), $guild, $post)) {
            abort(404);
        }

        $sessionId = $request->session()?->getId() ?? '';
        $recorded = ($this->recordPostViewAction)($post, $request->user(), $sessionId);

        return response()->json(['ok' => true, 'recorded' => $recorded]);
    }
}

