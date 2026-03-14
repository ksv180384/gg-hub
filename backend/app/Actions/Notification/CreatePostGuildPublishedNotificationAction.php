<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Guild\Models\Guild;
use Domains\Post\Models\Post;

/**
 * Оповещение автору поста: пост в гильдии был опубликован.
 */
class CreatePostGuildPublishedNotificationAction
{
    public function __invoke(Guild $guild, Post $post): ?Notification
    {
        $post->loadMissing(['user', 'character']);

        $user = $post->user;
        if (!$user) {
            return null;
        }

        $authorName = $post->character?->name ?? $user->name ?? 'Ваш персонаж';

        return Notification::create([
            'user_id' => $user->id,
            'message' => "Ваш пост «{$post->title}» от {$authorName} в гильдии «{$guild->name}» был опубликован.",
            'link' => '/my-posts/' . $post->id . '/edit',
        ]);
    }
}

