<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Post\Models\PostComment;
use InvalidArgumentException;

final class HidePostCommentAction
{
    private const PERMISSION_SLUG = 'skryvat-kommentarii';

    public function __invoke(PostComment $comment, User $user, ?string $reason = null): PostComment
    {
        $permissions = $user->getAllPermissionSlugs();
        if (! in_array(self::PERMISSION_SLUG, $permissions, true)) {
            throw new InvalidArgumentException('Недостаточно прав для скрытия комментария.');
        }

        $comment->update([
            'is_hidden' => true,
            'hidden_reason' => $reason !== null ? trim($reason) : $comment->hidden_reason,
        ]);

        return $comment->fresh();
    }
}
