<?php

namespace Domains\Poll\Models;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    protected $table = 'guild_polls';

    protected $fillable = [
        'guild_id',
        'created_by',
        'created_by_character_id',
        'title',
        'description',
        'is_anonymous',
        'is_closed',
        'closed_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creatorCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'created_by_character_id');
    }

    /** @return HasMany<PollOption> */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class, 'poll_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<PollVote> */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'poll_id');
    }
}
