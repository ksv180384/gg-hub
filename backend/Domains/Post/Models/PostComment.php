<?php

namespace Domains\Post\Models;

use App\Models\User;
use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['post_id', 'user_id', 'character_id', 'parent_id', 'replied_to_comment_id', 'body', 'is_hidden', 'hidden_reason', 'delete_reason'];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    public function repliedToComment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'replied_to_comment_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id')->orderBy('created_at');
    }
}
