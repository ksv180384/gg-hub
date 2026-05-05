<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use Domains\Post\Actions\RecordPostViewAction;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Публичная страница поста из раздела «Общие».
 */
final class GlobalPostController extends Controller
{
    public function __construct(
        private RecordPostViewAction $recordPostViewAction,
    ) {}

    /**
     * Полная версия одного поста общего журнала.
     * Доступно только если пост опубликован в общем разделе.
     */
    public function show(Request $request, Post $post): JsonResponse
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

        $sessionId = $request->session()?->getId() ?? '';
        ($this->recordPostViewAction)($post, $request->user(), $sessionId);

        $post->loadMissing(['character', 'character.user', 'user', 'game']);
        $post->refresh();
        $post->loadCount(['postComments as comments_count']);

        return response()->json(new PostResource($post));
    }
}

