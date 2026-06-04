<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\CreateGuildApplicationCommentHiddenNotificationAction;
use App\Actions\Notification\CreateGuildApplicationCommentDeletedNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\AdminDeleteGuildApplicationCommentRequest;
use App\Http\Requests\Guild\AdminHideGuildApplicationCommentRequest;
use App\Http\Resources\Guild\AdminGuildApplicationCommentResource;
use App\Services\GuildApplicationCommentSocketBroadcaster;
use Domains\Guild\Models\GuildApplicationComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Модерация комментариев к заявкам в гильдию в админке.
 */
class AdminGuildApplicationCommentController extends Controller
{
    public function __construct(
        private CreateGuildApplicationCommentHiddenNotificationAction $createGuildApplicationCommentHiddenNotificationAction,
        private CreateGuildApplicationCommentDeletedNotificationAction $createGuildApplicationCommentDeletedNotificationAction,
        private GuildApplicationCommentSocketBroadcaster $socketBroadcaster
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $applicationId = $request->query('application_id');
        $applicationId = is_numeric($applicationId) ? (int) $applicationId : null;

        $query = GuildApplicationComment::query()
            ->withTrashed()
            ->with([
                'character.user',
                'user',
                'application.guild',
            ])
            ->orderByDesc('created_at');

        if ($applicationId !== null) {
            $query->where('guild_application_id', $applicationId);
        }

        return AdminGuildApplicationCommentResource::collection($query->paginate($perPage));
    }

    public function hide(AdminHideGuildApplicationCommentRequest $request, GuildApplicationComment $comment): JsonResponse
    {
        $comment->is_hidden = true;
        $comment->hidden_reason = (string) $request->validated('reason');
        $comment->save();
        $comment->load(['character.user', 'user', 'application.guild']);
        ($this->createGuildApplicationCommentHiddenNotificationAction)($comment, (string) $request->validated('reason'));
        $this->broadcastCommentChanged($comment, 'hidden');

        return response()->json(new AdminGuildApplicationCommentResource($comment));
    }

    public function unhide(GuildApplicationComment $comment): JsonResponse
    {
        $comment->is_hidden = false;
        $comment->hidden_reason = null;
        $comment->save();
        $comment->load(['character.user', 'user', 'application.guild']);
        $this->broadcastCommentChanged($comment, 'unhidden');

        return response()->json(new AdminGuildApplicationCommentResource($comment));
    }

    public function destroy(AdminDeleteGuildApplicationCommentRequest $request, GuildApplicationComment $comment): JsonResponse
    {
        $reason = (string) $request->validated('reason');
        $comment->delete_reason = $reason;
        $comment->save();
        if (! $comment->trashed()) {
            $comment->delete();
        }
        ($this->createGuildApplicationCommentDeletedNotificationAction)($comment, $reason);
        $this->broadcastCommentChanged($comment, 'deleted');

        return response()->json(['message' => 'Комментарий удалён.']);
    }

    private function broadcastCommentChanged(GuildApplicationComment $comment, string $action): void
    {
        $comment->loadMissing('application');

        $guildId = (int) ($comment->application?->guild_id ?? 0);
        $applicationId = (int) ($comment->guild_application_id ?? 0);

        $this->socketBroadcaster->broadcastChangedFor($guildId, $applicationId, (int) $comment->id, $action);
    }
}
