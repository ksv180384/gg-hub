<?php

namespace Domains\Guild\Models;

use App\Models\User;
use Domains\Access\Models\GuildRole;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guild extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'localization_id',
        'server_id',
        'name',
        'slug',
        'description',
        'owner_id',
        'is_recruiting',
    ];

    protected function casts(): array
    {
        return [
            'is_recruiting' => 'boolean',
        ];
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GuildMember::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(GuildApplication::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(GuildRole::class);
    }
}
