<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostView;
use Illuminate\Support\Facades\DB;

/**
 * Учитывает просмотр поста.
 *
 * Один просмотр засчитывается один раз на пользователя/сессию.
 * Если пользователь сначала посмотрел анонимно (по session_id),
 * а затем авторизовался и посмотрел снова — засчитывается один раз (merge).
 */
final class RecordPostViewAction
{
    private const VIEWER_KEY_USER = 'user:%d';

    private const VIEWER_KEY_SESSION = 'session:%s';

    public function __invoke(Post $post, ?User $user, string $sessionId): void
    {
        if (empty($sessionId) && $user === null) {
            return;
        }

        DB::transaction(function () use ($post, $user, $sessionId): void {
            $userKey = $user !== null ? sprintf(self::VIEWER_KEY_USER, $user->id) : null;
            $sessionKey = ! empty($sessionId) ? sprintf(self::VIEWER_KEY_SESSION, $sessionId) : null;

            if ($user !== null) {
                $this->recordForAuthenticatedUser($post, $user, $userKey, $sessionKey);
            } else {
                $this->recordForAnonymous($post, $sessionKey);
            }
        });
    }

    private function recordForAuthenticatedUser(
        Post $post,
        User $user,
        string $userKey,
        ?string $sessionKey
    ): void {
        $existingByUser = PostView::where('post_id', $post->id)
            ->where('viewer_key', $userKey)
            ->first();

        if ($existingByUser !== null) {
            return;
        }

        $existingBySession = $sessionKey !== null
            ? PostView::where('post_id', $post->id)
                ->where('viewer_key', $sessionKey)
                ->first()
            : null;

        if ($existingBySession !== null) {
            $existingBySession->update([
                'user_id' => $user->id,
                'session_id' => null,
                'viewer_key' => $userKey,
            ]);

            return;
        }

        PostView::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'session_id' => null,
            'viewer_key' => $userKey,
        ]);

        $post->increment('views_count');
    }

    private function recordForAnonymous(Post $post, string $sessionKey): void
    {
        $existing = PostView::where('post_id', $post->id)
            ->where('viewer_key', $sessionKey)
            ->first();

        if ($existing !== null) {
            return;
        }

        PostView::create([
            'post_id' => $post->id,
            'user_id' => null,
            'session_id' => substr($sessionKey, strlen('session:')),
            'viewer_key' => $sessionKey,
        ]);

        $post->increment('views_count');
    }
}
