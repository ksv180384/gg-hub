<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Post\Models\PostComment;

/**
 * Оповещение автору комментария: комментарий был удалён модератором.
 * Вызывать до удаления комментария из БД (нужны post и guild для текста и ссылки).
 */
class CreateCommentDeletedNotificationAction
{
    public function __invoke(PostComment $comment): ?Notification
    {
        $comment->loadMissing(['post.guild', 'user']);

        $userId = $comment->user_id;
        if (! $userId) {
            return null;
        }

        $post = $comment->post;
        $postTitle = $post->title ?: 'Без названия';
        $guildName = $post->guild?->name ?? 'гильдии';

        $message = "Ваш комментарий к посту «{$postTitle}» в гильдии «{$guildName}» был удалён модератором.";

        $link = $post->guild_id
            ? '/guilds/' . $post->guild_id . '/posts/' . $post->id . '#comments'
            : null;

        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link ?? '',
        ]);
    }
}
