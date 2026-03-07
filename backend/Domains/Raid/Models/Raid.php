<?php

namespace Domains\Raid\Models;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Raid extends Model
{
    protected $fillable = [
        'guild_id',
        'parent_id',
        'leader_character_id',
        'created_by',
        'name',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Raid, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'leader_character_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            Character::class,
            'raid_members',
            'raid_id',
            'character_id'
        )->withPivot(['role', 'accepted_at', 'slot_index'])->withTimestamps();
    }
}
