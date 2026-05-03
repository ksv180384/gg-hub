<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Http\JsonResponse;

/**
 * Публичная страница поста из раздела «Общие».
 */
final class GlobalPostController extends Controller
{
    /**
     * Полная версия одного поста общего журнала.
     * Доступно только если пост опубликован в общем разделе.
     */
    public function show(Post $post): JsonResponse
    {
        if (! $post->is_visible_global) {
            abort(404);
        }

        if ($post->status_global !== PostStatus::Published->value) {
            abort(404);
        }

        if ($post->published_at_global === null) {
            abort(404);
        }

        $post->loadMissing(['character', 'character.user', 'user', 'game']);
        $post->loadCount(['postComments as comments_count']);

        return response()->json(new PostResource($post));
    }
}

