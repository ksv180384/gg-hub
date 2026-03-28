<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Guild\Models\GuildApplicationComment;

/**
 * Оповещение автору комментария к заявке: комментарий скрыт модератором с указанием причины.
 */
class CreateGuildApplicationCommentHiddenNotificationAction
{
    public function __invoke(GuildApplicationComment $comment, string $reason): ?Notification
    {
        $comment->loadMissing(['application.guild', 'user', 'character']);

        $userId = (int) $comment->user_id;
        if ($userId <= 0) {
            return null;
        }

        $application = $comment->application;
        $guildName = $application?->guild?->name ?? 'гильдии';
        $cleanReason = trim($reason);

        $message = "Ваш комментарий к заявке #{$comment->guild_application_id} в гильдии «{$guildName}» был скрыт модератором. Причина: {$cleanReason}";
        $link = $application?->guild_id
            ? '/guilds/' . $application->guild_id . '/applications'
            : '';

        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link,
        ]);
    }
}
