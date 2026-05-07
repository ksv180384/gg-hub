<?php

namespace App\Http\Controllers\Api;

use App\Actions\Post\StoreMyPostAction;
use App\Actions\Post\UpdateMyPostAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\Post\PostListResource;
use App\Http\Resources\Post\PostResource;
use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function __construct(
        private StoreMyPostAction $storeMyPostAction,
        private UpdateMyPostAction $updateMyPostAction,
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
        $post = ($this->storeMyPostAction)($request);
        $post->loadMissing(['character', 'character.user', 'user']);

        return new PostResource($post);
    }

    /**
     * Получить один пост текущего пользователя.
     * Владелец может просматривать свой пост при любом статусе (в т.ч. заблокированный).
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
     * Владелец может редактировать свой пост при любом статусе (в т.ч. заблокированный).
     */
    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $post = ($this->updateMyPostAction)($request, $post);
        $post->loadMissing(['character', 'character.user', 'user']);
        return new PostResource($post);
    }
}
