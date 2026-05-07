<?php

namespace App\Actions\Notification;

use App\Services\Notifications\GuildLinkBuilder;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет уведомление о создании/редактировании поста или комментария
 * через notification-gg-hub. Выполняется после отправки HTTP-ответа,
 * чтобы не замедлять запрос.
 *
 * Дополнительно: для постов гильдии шлёт Discord-оповещение
 * (`discord_notify_post_published`) — но только в момент фактической
 * публикации (статус guild = published), чтобы не светить посты,
 * которые ещё на модерации.
 */
class SendPostOrCommentNotificationAction
{
    public function __construct(
        private SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function postCreated(Post $post): void
    {
        $message = 'Создан пост: ' . $this->buildPostUrl($post);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();

        if ($post->guild_id && $post->status_guild === PostStatus::Published->value) {
            $this->sendGuildPostPublishedDiscord($post);
        }
    }

    public function postUpdated(Post $post): void
    {
        $message = 'Отредактирован пост: ' . $this->buildPostUrl($post);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    /**
     * Вызывается в момент утверждения поста модератором гильдии (переход pending → published).
     * Шлёт Discord-оповещение `discord_notify_post_published`.
     */
    public function postPublishedInGuild(Post $post): void
    {
        if (! $post->guild_id) {
            return;
        }
        $this->sendGuildPostPublishedDiscord($post);
    }

    public function commentCreated(Post $post, PostComment $comment): void
    {
        $message = 'Создан комментарий к посту: ' . $this->buildCommentUrl($post, $comment);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    public function commentUpdated(Post $post, PostComment $comment): void
    {
        $message = 'Отредактирован комментарий к посту: ' . $this->buildCommentUrl($post, $comment);
        $channel = $this->channel();
        dispatch(fn () => Log::channel($channel)->info($message))->afterResponse();
    }

    private function sendGuildPostPublishedDiscord(Post $post): void
    {
        $post->loadMissing(['guild.game']);
        /** @var Guild|null $guild */
        $guild = $post->guild;
        if (! $guild) {
            return;
        }

        $title = trim((string) $post->title);
        $titleLine = $title !== '' ? "«{$title}»" : '#' . $post->id;
        $url = $this->linkBuilder->postUrl($guild, (int) $post->id);
        $message = "Опубликован новый пост гильдии: {$titleLine}\n{$url}";

        ($this->sendGuildDiscordNotificationAction)(
            $guild,
            'discord_notify_post_published',
            $message,
        );
    }

    private function channel(): string
    {
        return (string) config('logging.notifications_channel', 'notification-hub');
    }

    private function buildPostUrl(Post $post): string
    {
        if ($post->guild_id) {
            $post->loadMissing(['guild.game']);
            /** @var Guild|null $guild */
            $guild = $post->guild;
            if ($guild) {
                return $this->linkBuilder->postUrl($guild, (int) $post->id);
            }
        }

        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        return $base . '/user/posts/' . $post->id;
    }

    private function buildCommentUrl(Post $post, PostComment $comment): string
    {
        if ($post->guild_id) {
            $post->loadMissing(['guild.game']);
            /** @var Guild|null $guild */
            $guild = $post->guild;
            if ($guild) {
                return $this->linkBuilder->postUrl($guild, (int) $post->id) . '#comment-' . $comment->id;
            }
        }

        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        return $base . '/posts/' . $post->id . '#comment-' . $comment->id;
    }
}
