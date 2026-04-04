<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Post\Models\PostComment;
use InvalidArgumentException;

final class UpdatePostCommentAction
{
    public function __invoke(PostComment $comment, User $user, string $body): PostComment
    {
        if ((int) $comment->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Вы можете редактировать только свои комментарии.');
        }

        $comment->body = $body;
        $comment->save();

        return $comment;
    }
}
