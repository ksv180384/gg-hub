<?php

namespace Domains\Character\Models;

use App\Models\User;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\GuildMember;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_id',
        'localization_id',
        'server_id',
        'name',
        'avatar',
    ];

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
        return $this->belongsToMany(\App\Models\GameClass::class, 'character_game_class');
    }

    /** Персонаж может состоять только в одной гильдии (в контексте игры/локации/сервера). */
    public function guildMember(): HasOne
    {
        return $this->hasOne(GuildMember::class);
    }
}
