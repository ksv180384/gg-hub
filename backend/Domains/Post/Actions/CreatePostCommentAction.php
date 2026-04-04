<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Guild\Models\GuildMember;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use InvalidArgumentException;

/**
 * Создание комментария к посту от имени персонажа.
 * Максимум 2 уровня вложенности.
 */
final class CreatePostCommentAction
{
    private const MAX_DEPTH = 2;

    public function __invoke(Post $post, User $user, int $characterId, string $body, ?int $parentId = null): PostComment
    {
        $parent = null;
        $depth = 0;

        $repliedToCommentId = null;

        if ($parentId !== null) {
            $parent = PostComment::query()
                ->where('post_id', $post->id)
                ->findOrFail($parentId);
            $depth = $parent->parent_id === null ? 1 : 2;

            if ($depth >= self::MAX_DEPTH) {
                $repliedToCommentId = $parent->id;
                $parentId = $parent->parent_id;
            }
        }

        $isValidCharacter = GuildMember::query()
            ->where('guild_id', $post->guild_id)
            ->where('character_id', $characterId)
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if (! $isValidCharacter) {
            throw new InvalidArgumentException('Персонаж не принадлежит пользователю или не состоит в гильдии поста.');
        }

        $comment = new PostComment([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'character_id' => $characterId,
            'parent_id' => $parentId,
            'replied_to_comment_id' => $repliedToCommentId,
            'body' => $body,
        ]);
        $comment->save();

        return $comment;
    }
}
