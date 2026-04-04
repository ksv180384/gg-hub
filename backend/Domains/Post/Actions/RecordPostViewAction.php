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

    /**
     * @return bool true, если просмотр засчитан впервые; false, если уже был учтён
     */
    public function __invoke(Post $post, ?User $user, string $sessionId): bool
    {
        if (empty($sessionId) && $user === null) {
            return false;
        }

        $recorded = false;
        DB::transaction(function () use ($post, $user, $sessionId, &$recorded): void {
            $userKey = $user !== null ? sprintf(self::VIEWER_KEY_USER, $user->id) : null;
            $sessionKey = ! empty($sessionId) ? sprintf(self::VIEWER_KEY_SESSION, $sessionId) : null;

            if ($user !== null) {
                $recorded = $this->recordForAuthenticatedUser($post, $user, $userKey, $sessionKey);
            } else {
                $recorded = $this->recordForAnonymous($post, $sessionKey);
            }
        });

        return $recorded;
    }

    private function recordForAuthenticatedUser(
        Post $post,
        User $user,
        string $userKey,
        ?string $sessionKey
    ): bool {
        $existingByUser = PostView::where('post_id', $post->id)
            ->where('viewer_key', $userKey)
            ->first();

        if ($existingByUser !== null) {
            return false;
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

            return false;
        }

        PostView::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'session_id' => null,
            'viewer_key' => $userKey,
        ]);

        $post->increment('views_count');

        return true;
    }

    private function recordForAnonymous(Post $post, string $sessionKey): bool
    {
        $existing = PostView::where('post_id', $post->id)
            ->where('viewer_key', $sessionKey)
            ->first();

        if ($existing !== null) {
            return false;
        }

        PostView::create([
            'post_id' => $post->id,
            'user_id' => null,
            'session_id' => substr($sessionKey, strlen('session:')),
            'viewer_key' => $sessionKey,
        ]);

        $post->increment('views_count');

        return true;
    }
}
