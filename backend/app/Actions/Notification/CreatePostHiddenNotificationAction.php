<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Post\Models\Post;
use Illuminate\Support\Facades\Lang;

/**
 * Оповещение автору поста: пост был скрыт администратором.
 */
class CreatePostHiddenNotificationAction
{
    public function __invoke(Post $post): ?Notification
    {
        $post->loadMissing(['user', 'character']);

        $user = $post->user;
        if (!$user) {
            return null;
        }

        $title = $post->title ?: Lang::get('post.no_title');
        $message = Lang::get('post.notification.hidden', ['title' => $title]);

        return Notification::create([
            'user_id' => $user->id,
            'message' => $message,
            'link' => '/my-posts/' . $post->id . '/edit',
        ]);
    }
}
