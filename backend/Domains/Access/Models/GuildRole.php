<?php

namespace Domains\Access\Models;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Роль в рамках гильдии (лидер, офицер, участник и т.д.).
 */
class GuildRole extends Model
{
    protected $table = 'guild_roles';

    protected $fillable = [
        'guild_id',
        'name',
        'slug',
        'priority',
    ];

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'guild_role_permission',
            'guild_role_id',
            'permission_id'
        );
    }
}
