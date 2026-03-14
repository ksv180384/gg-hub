<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Post\Models\PostComment;
use InvalidArgumentException;

final class DeletePostCommentAction
{
    public function __invoke(PostComment $comment, User $user): void
    {
        if ((int) $comment->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Вы можете удалять только свои комментарии.');
        }

        $comment->delete();
    }
}
