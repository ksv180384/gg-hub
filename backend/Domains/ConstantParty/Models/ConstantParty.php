<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Domains\Game\Models\Concerns\PreventsWritesOnMergingServer;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstantParty extends Model
{
    use PreventsWritesOnMergingServer;
    use SoftDeletes;

    protected $fillable = [
        'leader_character_id',
        'game_id',
        'localization_id',
        'server_id',
        'created_by_user_id',
        'name',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'leader_character_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function localization(): BelongsTo
    {
        return $this->belongsTo(Localization::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConstantPartyMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ConstantPartyInvitation::class);
    }
}
