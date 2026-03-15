<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Post\Models\PostComment;
use InvalidArgumentException;

final class UnhidePostCommentAction
{
    private const PERMISSION_SLUG = 'skryvat-kommentarii';

    public function __invoke(PostComment $comment, User $user): PostComment
    {
        $permissions = $user->getAllPermissionSlugs();
        if (! in_array(self::PERMISSION_SLUG, $permissions, true)) {
            throw new InvalidArgumentException('Недостаточно прав для отображения комментария.');
        }

        $comment->update(['is_hidden' => false]);

        return $comment->fresh();
    }
}
