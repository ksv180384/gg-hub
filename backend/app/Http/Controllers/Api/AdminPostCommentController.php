<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\CreateCommentDeletedNotificationAction;
use App\Actions\Notification\CreateCommentHiddenNotificationAction;
use App\Actions\Notification\CreateCommentShownNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\AdminPostCommentResource;
use Domains\Post\Actions\DeletePostCommentAction;
use Domains\Post\Actions\HidePostCommentAction;
use Domains\Post\Actions\ListAdminPostCommentsAction;
use Domains\Post\Actions\UnhidePostCommentAction;
use Domains\Post\Models\PostComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

/**
 * Модерация комментариев к постам в админке.
 */
class AdminPostCommentController extends Controller
{
    public function __construct(
        private ListAdminPostCommentsAction $listAdminPostCommentsAction,
        private HidePostCommentAction $hidePostCommentAction,
        private UnhidePostCommentAction $unhidePostCommentAction,
        private DeletePostCommentAction $deletePostCommentAction,
        private CreateCommentHiddenNotificationAction $createCommentHiddenNotificationAction,
        private CreateCommentShownNotificationAction $createCommentShownNotificationAction,
        private CreateCommentDeletedNotificationAction $createCommentDeletedNotificationAction,
    ) {}

    /**
     * Список комментариев для модерации (все в одном месте, с привязкой к посту и гильдии).
     * Query: post_id — при указании возвращаются только комментарии выбранного поста.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $postId = $request->query('post_id');
        $postId = is_numeric($postId) ? (int) $postId : null;

        $paginator = ($this->listAdminPostCommentsAction)($perPage, $postId);

        return AdminPostCommentResource::collection($paginator);
    }

    /**
     * Скрыть комментарий. Требуется право skryvat-kommentarii.
     */
    public function hide(Request $request, PostComment $comment): JsonResponse
    {
        try {
            $comment = ($this->hidePostCommentAction)($comment, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        ($this->createCommentHiddenNotificationAction)($comment);

        return response()->json(new AdminPostCommentResource($comment));
    }

    /**
     * Показать комментарий (снять скрытие). Требуется право skryvat-kommentarii.
     */
    public function unhide(Request $request, PostComment $comment): JsonResponse
    {
        try {
            $comment = ($this->unhidePostCommentAction)($comment, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        ($this->createCommentShownNotificationAction)($comment);

        return response()->json(new AdminPostCommentResource($comment));
    }

    /**
     * Удалить комментарий. Требуется право udaliat-kommentarii или автор комментария.
     */
    public function destroy(Request $request, PostComment $comment): JsonResponse
    {
        try {
            ($this->createCommentDeletedNotificationAction)($comment);
            ($this->deletePostCommentAction)($comment, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'Комментарий удалён.']);
    }
}
