<?php

namespace App\Actions\Notification;

use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет уведомление о создании/редактировании поста или комментария
 * через notification-gg-hub. Выполняется после отправки HTTP-ответа,
 * чтобы не замедлять запрос.
 */
class SendPostOrCommentNotificationAction
{
    public function postCreated(Post $post): void
    {
        $message = 'Создан пост: ' . $this->buildPostUrl($post);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    public function postUpdated(Post $post): void
    {
        $message = 'Отредактирован пост: ' . $this->buildPostUrl($post);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    public function commentCreated(Post $post, PostComment $comment): void
    {
        $message = 'Создан комментарий к посту: ' . $this->buildCommentUrl($post, $comment);
        $channel = $this->channel();
        Log::channel('single')->error('Notification send', [
            'channel' => $channel,
            'message' => $message,
        ]);
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    public function commentUpdated(Post $post, PostComment $comment): void
    {
        $message = 'Отредактирован комментарий к посту: ' . $this->buildCommentUrl($post, $comment);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    private function channel(): string
    {
        return (string) config('logging.notifications_channel', 'notification-hub');
    }

    private function buildPostUrl(Post $post): string
    {
        $base = $this->baseUrlWithGameSubdomain($post);
        if ($post->guild_id) {
            return $base . '/guilds/' . $post->guild_id . '/posts/' . $post->id;
        }

        return $base . '/user/posts/' . $post->id;
    }

    private function buildCommentUrl(Post $post, PostComment $comment): string
    {
        $base = $this->baseUrlWithGameSubdomain($post);
        if ($post->guild_id) {
            return $base . '/guilds/' . $post->guild_id . '/posts/' . $post->id . '#comment-' . $comment->id;
        }

        return $base . '/posts/' . $post->id . '#comment-' . $comment->id;
    }

    /**
     * Базовый URL фронтенда с учётом субдомена игры (например tl.gg-hub.local).
     */
    private function baseUrlWithGameSubdomain(Post $post): string
    {
        $raw = rtrim(config('app.frontend_url', config('app.url')), '/');
        $parsed = parse_url($raw);
        $scheme = ($parsed['scheme'] ?? 'http') . '://';
        $host = $parsed['host'] ?? 'localhost';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        $gameSlug = null;
        if ($post->guild_id) {
            $post->loadMissing(['guild.game']);
            $gameSlug = $post->guild?->game?->slug;
        }

        if ($gameSlug) {
            $host = $gameSlug . '.' . $host;
        }

        return $scheme . $host . $port;
    }
}
