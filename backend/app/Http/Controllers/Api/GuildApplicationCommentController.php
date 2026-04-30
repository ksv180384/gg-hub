<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\SendGuildApplicationCommentNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\StoreGuildApplicationCommentRequest;
use App\Http\Requests\Guild\UpdateGuildApplicationCommentRequest;
use App\Http\Resources\Guild\GuildApplicationCommentResource;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildApplicationComment;
use Domains\Guild\Models\GuildMember;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuildApplicationCommentController extends Controller
{
    private const MAX_DEPTH = 2;

    public function __construct(
        private SendGuildApplicationCommentNotificationAction $sendGuildApplicationCommentNotificationAction
    ) {}

    public function index(Request $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if (! $this->canAccessApplication($request->user()?->id, $guild, $application)) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }

        $comments = $application->comments()
            ->whereNull('parent_id')
            ->with([
                'character.user',
                'user:id,name,avatar',
                'children.character.user',
                'children.user:id,name,avatar',
                'children.repliedToComment.character.user',
                'children.repliedToComment.user:id,name',
                'children.parent.character.user',
                'children.parent.user:id,name',
            ])
            ->orderBy('created_at')
            ->get();
        $characters = $this->getAvailableCharacters($request->user()->id, $guild, $application);

        return response()->json([
            'data' => GuildApplicationCommentResource::collection($comments),
            'meta' => [
                'my_characters' => $characters->map(fn ($c) => [
                    'id' => $c['id'],
                    'name' => $c['name'],
                    'avatar_url' => $c['avatar_url'],
                ])->values(),
                'default_character_id' => $characters->first()['id'] ?? null,
            ],
        ]);
    }

    public function store(StoreGuildApplicationCommentRequest $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if (! $this->canAccessApplication($request->user()?->id, $guild, $application)) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }

        $availableCharacters = $this->getAvailableCharacters($request->user()->id, $guild, $application);
        $characterId = (int) $request->validated('character_id');
        if (! $availableCharacters->contains(fn ($c) => (int) $c['id'] === $characterId)) {
            return response()->json(['message' => 'Нельзя писать комментарий от этого персонажа.'], 422);
        }

        $parentId = $request->validated('parent_id');
        $repliedToCommentId = null;
        if ($parentId !== null) {
            $parent = GuildApplicationComment::query()
                ->where('guild_application_id', $application->id)
                ->findOrFail((int) $parentId);
            $depth = $parent->parent_id === null ? 1 : 2;
            if ($depth >= self::MAX_DEPTH) {
                $repliedToCommentId = $parent->id;
                $parentId = $parent->parent_id;
            }
        }

        $comment = GuildApplicationComment::query()->create([
            'guild_application_id' => $application->id,
            'user_id' => (int) $request->user()->id,
            'character_id' => $characterId,
            'parent_id' => $parentId,
            'replied_to_comment_id' => $repliedToCommentId,
            'body' => $request->validated('body'),
        ]);
        $comment->load([
            'character.user',
            'user:id,name,avatar',
            'parent.character.user',
            'parent.user:id,name',
            'repliedToComment.character.user',
            'repliedToComment.user:id,name',
        ]);
        $application->loadMissing('guild.game');
        $this->sendGuildApplicationCommentNotificationAction->commentCreated($application, $comment);

        return response()->json(new GuildApplicationCommentResource($comment), 201);
    }

    public function update(
        UpdateGuildApplicationCommentRequest $request,
        Guild $guild,
        GuildApplication $application,
        GuildApplicationComment $comment
    ): JsonResponse {
        if (! $this->canAccessApplication($request->user()?->id, $guild, $application)
            || (int) $comment->guild_application_id !== (int) $application->id) {
            return response()->json(['message' => 'Комментарий не найден.'], 404);
        }

        if ((int) $comment->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Можно редактировать только свои комментарии.'], 403);
        }

        $comment->body = $request->validated('body');
        $comment->save();
        $comment->load([
            'character.user',
            'user:id,name,avatar',
            'parent.character.user',
            'parent.user:id,name',
            'repliedToComment.character.user',
            'repliedToComment.user:id,name',
        ]);

        return response()->json(new GuildApplicationCommentResource($comment));
    }

    public function destroy(Request $request, Guild $guild, GuildApplication $application, GuildApplicationComment $comment): JsonResponse
    {
        if (! $this->canAccessApplication($request->user()?->id, $guild, $application)
            || (int) $comment->guild_application_id !== (int) $application->id) {
            return response()->json(['message' => 'Комментарий не найден.'], 404);
        }

        if ((int) $comment->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Можно удалять только свои комментарии.'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Комментарий удалён.']);
    }

    private function canAccessApplication(?int $userId, Guild $guild, GuildApplication $application): bool
    {
        if (! $userId || (int) $application->guild_id !== (int) $guild->id) {
            return false;
        }

        $application->loadMissing('character:id,user_id');
        $isOwner = (int) ($application->character?->user_id ?? 0) === $userId;
        if ($isOwner) {
            return true;
        }

        return GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }

    /**
     * Для участника гильдии — все его персонажи в этой гильдии по возрастанию joined_at.
     * Для автора заявки (если не участник) — только персонаж заявки.
     *
     * @return Collection<int, array{id:int,name:string,avatar_url:?string}>
     */
    private function getAvailableCharacters(int $userId, Guild $guild, GuildApplication $application): Collection
    {
        $members = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $userId))
            ->with('character.user')
            ->orderBy('joined_at')
            ->get();

        if ($members->isNotEmpty()) {
            return $members
                ->filter(fn ($m) => $m->character)
                ->map(fn ($m) => [
                    'id' => (int) $m->character->id,
                    'name' => $m->character->name,
                    'avatar_url' => $m->character->resolved_avatar_url,
                ])
                ->values();
        }

        $application->loadMissing('character.user');
        if ((int) ($application->character?->user_id ?? 0) === $userId && $application->character) {
            return collect([[
                'id' => (int) $application->character->id,
                'name' => $application->character->name,
                'avatar_url' => $application->character->resolved_avatar_url,
            ]]);
        }

        return collect();
    }
}
