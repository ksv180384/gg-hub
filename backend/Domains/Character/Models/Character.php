<?php

namespace Domains\Character\Models;

use App\Core\Traits\HasFilter;
use App\Services\CharacterAvatarService;
use App\Services\UserAvatarService;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\Game\Models\Concerns\PreventsWritesOnMergingServer;
use Domains\Game\Models\Game;
use Domains\Game\Models\GameClass;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\GuildMember;
use Domains\Tag\Models\Tag;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Character extends Model
{
    use HasFactory;
    use HasFilter;
    use PreventsWritesOnMergingServer;

    protected $fillable = [
        'user_id',
        'game_id',
        'localization_id',
        'server_id',
        'name',
        'avatar',
        'use_profile_avatar',
        'is_main',
    ];

    protected function casts(): array
    {
        return [
            'use_profile_avatar' => 'boolean',
            'is_main' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** Классы персонажа в рамках игры (многие ко многим). */
    public function gameClasses(): BelongsToMany
    {
        return $this->belongsToMany(GameClass::class, 'character_game_class');
    }

    /** Персонаж может состоять только в одной гильдии (в контексте игры/локации/сервера). */
    public function guildMember(): HasOne
    {
        return $this->hasOne(GuildMember::class);
    }

    public function constantPartyMember(): HasOne
    {
        return $this->hasOne(ConstantPartyMember::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'character_tag');
    }

    /** Теги персонажа в контексте гильдии (pivot character_guild_tag). */
    public function characterGuildTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'character_guild_tag', 'character_id', 'tag_id')
            ->withPivot('guild_id');
    }

    public function getResolvedAvatarUrlAttribute(): ?string
    {
        if ($this->use_profile_avatar && $this->user?->avatar) {
            return Storage::disk('public')->url(
                UserAvatarService::smallPath($this->user->avatar)
            );
        }

        if ($this->avatar) {
            return Storage::disk('public')->url(
                CharacterAvatarService::smallPath($this->avatar)
            );
        }

        return null;
    }
}
