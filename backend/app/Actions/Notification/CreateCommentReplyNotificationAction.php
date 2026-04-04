<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;

/**
 * Создаёт оповещение пользователю, которому ответили:
 * - при ответе на пост — автору поста;
 * - при ответе на комментарий — автору комментария.
 * Не отправляет оповещение самому себе.
 */
class CreateCommentReplyNotificationAction
{
    public function __invoke(Post $post, PostComment $comment): ?Notification
    {
        $comment->loadMissing(['parent', 'repliedToComment', 'character', 'user']);
        $post->loadMissing(['user', 'guild']);

        $recipientUserId = $this->getRecipientUserId($post, $comment);
        if ($recipientUserId === null || (int) $recipientUserId === (int) $comment->user_id) {
            return null;
        }

        $authorName = $comment->character?->name ?? $comment->user?->name ?? 'Пользователь';
        $guildName = $post->guild?->name ?? 'гильдии';

        if ($comment->parent_id === null) {
            $message = $post->title
                ? "{$authorName} ответил(а) на ваш пост «{$post->title}» в гильдии «{$guildName}»."
                : "{$authorName} ответил(а) на ваш пост в гильдии «{$guildName}».";
        } else {
            $message = $post->title
                ? "{$authorName} ответил(а) на ваш комментарий к посту «{$post->title}» в гильдии «{$guildName}»."
                : "{$authorName} ответил(а) на ваш комментарий в гильдии «{$guildName}».";
        }

        $link = '/guilds/' . $post->guild_id . '/posts/' . $post->id . '#comment-' . $comment->id;

        return Notification::create([
            'user_id' => $recipientUserId,
            'message' => $message,
            'link' => $link,
        ]);
    }

    private function getRecipientUserId(Post $post, PostComment $comment): ?int
    {
        if ($comment->parent_id === null) {
            return $post->user_id;
        }

        $repliedTo = $comment->replied_to_comment_id
            ? $comment->repliedToComment
            : $comment->parent;

        return $repliedTo?->user_id;
    }
}
