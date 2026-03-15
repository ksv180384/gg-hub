<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostListResource;
use App\Http\Resources\Post\PostResource;
use App\Actions\Notification\CreatePostBlockedNotificationAction;
use App\Actions\Notification\CreatePostHiddenNotificationAction;
use App\Actions\Notification\CreatePostGlobalPublishedNotificationAction;
use App\Actions\Notification\CreatePostGlobalRejectedNotificationAction;
use App\Actions\Notification\CreatePostGuildPublishedNotificationAction;
use App\Actions\Notification\CreatePostGuildRejectedNotificationAction;
use Domains\Post\Actions\BlockPostAction;
use Domains\Post\Actions\HidePostAction;
use Domains\Post\Actions\UnblockPostAction;
use Domains\Post\Actions\PublishGlobalPostAction;
use Domains\Post\Actions\PublishGuildPostAction;
use Domains\Post\Actions\RejectGlobalPostAction;
use Domains\Post\Actions\RejectGuildPostAction;
use Domains\Game\Models\Game;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Журнал всех постов (админка).
 */
class AdminPostController extends Controller
{
    public function __construct(
        private PublishGuildPostAction $publishGuildPostAction,
        private PublishGlobalPostAction $publishGlobalPostAction,
        private RejectGuildPostAction $rejectGuildPostAction,
        private RejectGlobalPostAction $rejectGlobalPostAction,
        private BlockPostAction $blockPostAction,
        private HidePostAction $hidePostAction,
        private UnblockPostAction $unblockPostAction,
        private CreatePostGuildPublishedNotificationAction $createPostGuildPublishedNotificationAction,
        private CreatePostGuildRejectedNotificationAction $createPostGuildRejectedNotificationAction,
        private CreatePostGlobalPublishedNotificationAction $createPostGlobalPublishedNotificationAction,
        private CreatePostGlobalRejectedNotificationAction $createPostGlobalRejectedNotificationAction,
        private CreatePostBlockedNotificationAction $createPostBlockedNotificationAction,
        private CreatePostHiddenNotificationAction $createPostHiddenNotificationAction,
    ) {}

    /**
     * Список всех постов.
     * Query:
     *   filter=pending_global — только посты, ожидающие публикации в раздел «Общие»;
     *   scope=global — только посты общего журнала (is_visible_global = true);
     *   scope=guild — только посты для журналов гильдий (guild_id не null);
     *   guild_id — при scope=guild фильтр по конкретной гильдии;
     *   game_id — фильтр по игре.
     *   status — при scope=global фильтр по status_global; при scope=guild — по status_guild (pending, published, draft, hidden, rejected, blocked).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::query()
            ->withCount(['postComments as comments_count'])
            ->with(['character', 'character.user', 'user', 'game'])
            ->orderByDesc('created_at');

        $filter = $request->query('filter');
        if ($filter === 'pending_global') {
            $query->where('status_global', PostStatus::Pending->value);
        }

        $scope = $request->query('scope');
        if ($scope === 'global') {
            $query->where('is_visible_global', true);
        }
        if ($scope === 'guild') {
            $query->whereNotNull('guild_id');
            $guildId = $request->query('guild_id');
            if ($guildId && is_numeric($guildId)) {
                $query->where('guild_id', (int) $guildId);
            }
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '' && \in_array($status, PostStatus::values(), true)) {
            if ($scope === 'global') {
                $query->where('status_global', $status);
            }
            if ($scope === 'guild') {
                $query->where('status_guild', $status);
            }
        }

        $gameId = $request->query('game_id');
        if ($gameId && is_numeric($gameId)) {
            $query->where('game_id', (int) $gameId);
        }

        $posts = $query->get();

        $pendingGlobalCount = Post::query()
            ->where('status_global', PostStatus::Pending->value)
            ->count();

        $guildsWithPosts = [];
        if ($scope === 'guild') {
            $guildIds = Post::query()->whereNotNull('guild_id')->distinct()->pluck('guild_id');
            $guildsWithPosts = Guild::query()
                ->whereIn('id', $guildIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Guild $g) => ['id' => $g->id, 'name' => $g->name])
                ->values()
                ->all();
        }

        $gameIds = Post::query()->whereNotNull('game_id')->distinct()->pluck('game_id');
        $gamesWithPosts = Game::query()
            ->whereIn('id', $gameIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Game $g) => ['id' => $g->id, 'name' => $g->name])
            ->values()
            ->all();

        return PostListResource::collection($posts)
            ->additional([
                'meta' => [
                    'pending_global_count' => $pendingGlobalCount,
                    'guilds' => $guildsWithPosts,
                    'games' => $gamesWithPosts,
                ],
            ]);
    }

    /**
     * Количество постов, ожидающих публикации в общий журнал.
     */
    public function pendingCount(Request $request): JsonResponse
    {
        $count = Post::query()
            ->where('status_global', PostStatus::Pending->value)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Подсказки постов по названию (для фильтра комментариев в админке).
     * Query: q — строка поиска по title. Возвращает до 15 постов.
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = $request->query('q');
        if (! is_string($q) || trim($q) === '') {
            return response()->json([]);
        }

        $posts = Post::query()
            ->select('id', 'title', 'guild_id')
            ->with('guild:id,name')
            ->where('title', 'like', '%' . trim($q) . '%')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $items = $posts->map(function (Post $post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'guild_id' => $post->guild_id,
                'guild_name' => $post->guild?->name,
            ];
        });

        return response()->json($items->all());
    }

    /**
     * Один пост для админки.
     */
    public function show(Post $post): JsonResponse
    {
        $post->loadMissing(['character', 'character.user', 'user', 'guild', 'game']);
        $post->loadCount(['postComments as comments_count']);

        return response()->json(new PostResource($post));
    }

    /**
     * Утвердить пост (гильдия и/или общий журнал).
     */
    public function publish(Post $post): JsonResponse
    {
        if ($post->status_global === PostStatus::Pending->value) {
            $post = ($this->publishGlobalPostAction)($post);
            ($this->createPostGlobalPublishedNotificationAction)($post);
        }

        if ($post->status_guild === PostStatus::Pending->value) {
            $guild = $post->guild;
            if ($guild) {
                $post = ($this->publishGuildPostAction)($guild, $post);
                ($this->createPostGuildPublishedNotificationAction)($guild, $post);
            }
        }

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Отклонить пост (гильдия и/или общий журнал).
     */
    public function reject(Post $post): JsonResponse
    {
        if ($post->status_global === PostStatus::Pending->value) {
            $post = ($this->rejectGlobalPostAction)($post);
            ($this->createPostGlobalRejectedNotificationAction)($post);
        }

        if ($post->status_guild === PostStatus::Pending->value) {
            $guild = $post->guild;
            if ($guild) {
                $post = ($this->rejectGuildPostAction)($guild, $post);
                ($this->createPostGuildRejectedNotificationAction)($guild, $post);
            }
        }

        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Заблокировать пост (скрыть из журналов).
     */
    public function block(Post $post): JsonResponse
    {
        $post = ($this->blockPostAction)($post);
        ($this->createPostBlockedNotificationAction)($post);
        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Скрыть пост (убрать из журналов, status_global и status_guild → hidden).
     */
    public function hide(Post $post): JsonResponse
    {
        $post = ($this->hidePostAction)($post);
        ($this->createPostHiddenNotificationAction)($post);
        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }

    /**
     * Разблокировать пост: в разделах со статусом blocked выставить hidden.
     */
    public function unblock(Post $post): JsonResponse
    {
        $post = ($this->unblockPostAction)($post);
        $post->loadMissing(['character', 'character.user', 'user']);

        return response()->json(new PostResource($post));
    }
}
