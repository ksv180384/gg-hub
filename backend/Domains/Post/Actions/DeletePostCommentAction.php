<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Post\Models\PostComment;
use InvalidArgumentException;

final class DeletePostCommentAction
{
    private const PERMISSION_DELETE_SLUG = 'udaliat-kommentarii';

    public function __invoke(PostComment $comment, User $user): void
    {
        $permissions = $user->getAllPermissionSlugs();
        $canModerate = in_array(self::PERMISSION_DELETE_SLUG, $permissions, true);
        $isAuthor = (int) $comment->user_id === (int) $user->id;

        if (! $canModerate && ! $isAuthor) {
            throw new InvalidArgumentException('Вы можете удалять только свои комментарии.');
        }

        $comment->delete();
    }
}
