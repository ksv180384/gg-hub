<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Post\Models\Post;

/**
 * Оповещение автору поста: пост для общего журнала был отклонён.
 */
class CreatePostGlobalRejectedNotificationAction
{
    public function __invoke(Post $post): ?Notification
    {
        $post->loadMissing(['user', 'character']);

        $user = $post->user;
        if (!$user) {
            return null;
        }

        $title = $post->title ?: 'без заголовка';

        return Notification::create([
            'user_id' => $user->id,
            'message' => "Ваш пост «{$title}» был отклонён модерацией и не опубликован в общий журнал.",
            'link' => '/my-posts/' . $post->id . '/edit',
        ]);
    }
}
