<?php

namespace Domains\Post\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Просмотр поста пользователем или сессией.
 *
 * Один просмотр засчитывается один раз:
 * - для авторизованного: по user_id
 * - для анонима: по session_id
 *
 * При авторизации: если пост уже был просмотрен по session_id,
 * при повторном просмотре под user_id запись обновляется (merge),
 * чтобы не дублировать счётчик.
 */
class PostView extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'session_id',
        'viewer_key',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
