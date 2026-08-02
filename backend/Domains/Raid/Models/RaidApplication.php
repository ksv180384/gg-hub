<?php

namespace Domains\Raid\Models;

use Domains\Character\Models\Character;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaidApplication extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'raid_id',
        'character_id',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
