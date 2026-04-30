<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\CreateCommentReplyNotificationAction;
use App\Actions\Notification\SendPostOrCommentNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostCommentRequest;
use App\Http\Requests\Post\UpdatePostCommentRequest;
use App\Http\Resources\Post\PostCommentResource;
use Domains\Guild\Models\Guild;
use Domains\Post\Actions\CanViewGuildPostAction;
use Domains\Post\Actions\CreatePostCommentAction;
use Domains\Post\Actions\DeletePostCommentAction;
use Domains\Post\Actions\ListPostCommentsAction;
use Domains\Post\Actions\UpdatePostCommentAction;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class GuildPostCommentController extends Controller
{
    public function __construct(
        private CanViewGuildPostAction $canViewGuildPostAction,
        private ListPostCommentsAction $listPostCommentsAction,
        private CreatePostCommentAction $createPostCommentAction,
        private UpdatePostCommentAction $updatePostCommentAction,
        private DeletePostCommentAction $deletePostCommentAction,
        private CreateCommentReplyNotificationAction $createCommentReplyNotificationAction,
        private SendPostOrCommentNotificationAction $sendPostOrCommentNotificationAction,
    ) {}

    /**
     * Список комментариев к посту гильдии.
     */
    public function index(Request $request, Guild $guild, Post $post): AnonymousResourceCollection|JsonResponse
    {
        if (! ($this->canViewGuildPostAction)($request->user(), $guild, $post)) {
            abort(404);
        }

        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        $comments = ($this->listPostCommentsAction)($post);

        return PostCommentResource::collection($comments);
    }

    /**
     * Создание комментария к посту гильдии.
     */
    public function store(StorePostCommentRequest $request, Guild $guild, Post $post): JsonResponse
    {
        if (! ($this->canViewGuildPostAction)($request->user(), $guild, $post)) {
            abort(404);
        }

        if ((int) $post->guild_id !== (int) $guild->id) {
            abort(404);
        }

        try {
            $comment = ($this->createPostCommentAction)(
                $post,
                $request->user(),
                (int) $request->validated('character_id'),
                $request->validated('body'),
                $request->validated('parent_id')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ($this->createCommentReplyNotificationAction)($post, $comment);
        $this->sendPostOrCommentNotificationAction->commentCreated($post, $comment);

        $comment->load(['character', 'user', 'parent.character', 'repliedToComment.character']);

        return response()->json(new PostCommentResource($comment), 201);
    }

    /**
     * Обновление своего комментария.
     */
    public function update(UpdatePostCommentRequest $request, Guild $guild, Post $post, PostComment $comment): JsonResponse
    {
        if (! ($this->canViewGuildPostAction)($request->user(), $guild, $post)) {
            abort(404);
        }

        if ((int) $post->guild_id !== (int) $guild->id || (int) $comment->post_id !== (int) $post->id) {
            abort(404);
        }

        try {
            $comment = ($this->updatePostCommentAction)($comment, $request->user(), $request->validated('body'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $this->sendPostOrCommentNotificationAction->commentUpdated($post, $comment);

        $comment->load(['character', 'user', 'parent.character', 'repliedToComment.character']);

        return response()->json(new PostCommentResource($comment));
    }

    /**
     * Удаление своего комментария.
     */
    public function destroy(Request $request, Guild $guild, Post $post, PostComment $comment): JsonResponse
    {
        if (! ($this->canViewGuildPostAction)($request->user(), $guild, $post)) {
            abort(404);
        }

        if ((int) $post->guild_id !== (int) $guild->id || (int) $comment->post_id !== (int) $post->id) {
            abort(404);
        }

        try {
            ($this->deletePostCommentAction)($comment, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'Комментарий удалён.']);
    }
}
