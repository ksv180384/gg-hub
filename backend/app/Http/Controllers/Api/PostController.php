<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\CreatePostPendingGuildModerationNotificationAction;
use App\Actions\Notification\SendPostOrCommentTelegramNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\Post\PostListResource;
use App\Http\Resources\Post\PostResource;
use Domains\Guild\Models\Guild;
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
        private SendPostOrCommentTelegramNotificationAction $sendPostOrCommentTelegramNotificationAction,
    ) {}

    /**
     * Список постов текущего пользователя.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->where('user_id', $request->user()->id)
            ->with(['character', 'character.user', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return PostListResource::collection($posts);
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
        // published_at_guild задаётся в ApplyPostModerationRulesAction (now при праве publikovat-post, null при модерации)

        $post = ($this->createPostAction)($data);

        $this->sendPostOrCommentTelegramNotificationAction->postCreated($post);

        if ($result['notify_guild_id'] !== null) {
            $guild = Guild::query()->find($result['notify_guild_id']);
            if ($guild) {
                $link = '/guilds/' . $guild->id . '/posts/' .  $post->id;
                ($this->createPostPendingGuildModerationNotificationAction)($guild, $post, $link);
            }
        }

        $post->loadMissing(['character', 'character.user', 'user']);

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

        $post->loadMissing(['character', 'character.user', 'user']);

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
        $post->loadMissing(['character', 'character.user', 'user']);

        // Уведомляем модераторов только при новом переводе поста на модерацию (не при каждом сохранении уже ожидающего)
        $wasAlreadyPending = $previousStatusGuild === 'pending';
        if ($result['notify_guild_id'] !== null && !$wasAlreadyPending) {
            $guild = Guild::query()->find($result['notify_guild_id']);
            if ($guild) {
                $link = '/guilds/' . $guild->id . '/posts/' . $post->id;
                ($this->createPostPendingGuildModerationNotificationAction)($guild, $post, $link);
            }
        }

        return new PostResource($post);
    }
}
