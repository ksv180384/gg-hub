<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\CreatePostPendingGuildModerationNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\Post\PostResource;
use App\Models\Guild;
use Domains\Post\Actions\ApplyPostModerationRulesAction;
use Domains\Post\Actions\BuildPostDataFromRequestAction;
use Domains\Post\Actions\CreatePostAction;
use Domains\Post\Actions\UpdatePostAction;
use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function __construct(
        private CreatePostAction $createPostAction,
        private BuildPostDataFromRequestAction $buildPostDataFromRequestAction,
        private UpdatePostAction $updatePostAction,
        private ApplyPostModerationRulesAction $applyPostModerationRulesAction,
        private CreatePostPendingGuildModerationNotificationAction $createPostPendingGuildModerationNotificationAction,
    ) {}

    /**
     * Список постов текущего пользователя.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return PostResource::collection($posts);
    }

    /**
     * Создание поста текущим пользователем.
     */
    public function store(StorePostRequest $request): PostResource
    {
        $user = $request->user();

        $data = ($this->buildPostDataFromRequestAction)($request);

        $result = ($this->applyPostModerationRulesAction)($data, $user);
        $data = $result['data'];

        $data['user_id'] = $user->id;
        $data['published_at_global'] = null;
        $data['published_at_guild'] = null;

        $post = ($this->createPostAction)($data);

        if ($result['notify_guild_id'] !== null) {
            $guild = Guild::query()->find($result['notify_guild_id']);
            if ($guild) {
                $link = '/guilds/' . $guild->id . '/posts';
                ($this->createPostPendingGuildModerationNotificationAction)($guild, $post, $link);
            }
        }

        return new PostResource($post);
    }

    /**
     * Получить один пост текущего пользователя.
     */
    public function show(Request $request, Post $post): PostResource
    {
        if ($post->user_id !== $request->user()->id) {
            abort(404);
        }

        return new PostResource($post);
    }

    /**
     * Обновление поста текущего пользователя.
     */
    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $user = $request->user();

        // Статус в гильдии до применения новых данных (нужен, чтобы корректно понять переход в pending)
        $previousStatusGuild = $post->status_guild;

        $data = ($this->buildPostDataFromRequestAction)($request);

        $result = ($this->applyPostModerationRulesAction)($data, $user);
        $data = $result['data'];

        $post = ($this->updatePostAction)($post, $data);

        // Уведомляем модераторов только при новом переводе поста на модерацию (не при каждом сохранении уже ожидающего)
        $wasAlreadyPending = $previousStatusGuild === 'pending';
        if ($result['notify_guild_id'] !== null && !$wasAlreadyPending) {
            $guild = Guild::query()->find($result['notify_guild_id']);
            if ($guild) {
                $link = '/guilds/' . $guild->id . '/posts';
                ($this->createPostPendingGuildModerationNotificationAction)($guild, $post, $link);
            }
        }

        return new PostResource($post);
    }
}
