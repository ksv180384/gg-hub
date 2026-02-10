<?php

namespace Domains\Raid\Models;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Raid extends Model
{
    protected $fillable = [
        'guild_id',
        'created_by',
        'name',
        'description',
        'scheduled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            \Domains\Character\Models\Character::class,
            'raid_members',
            'raid_id',
            'character_id'
        )->withPivot(['role', 'accepted_at'])->withTimestamps();
    }
}
