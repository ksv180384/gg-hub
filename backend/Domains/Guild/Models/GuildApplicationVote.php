<?php

namespace Domains\Guild\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildApplicationVote extends Model
{
    protected $fillable = [
        'guild_application_id',
        'user_id',
        'vote',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(GuildApplication::class, 'guild_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
