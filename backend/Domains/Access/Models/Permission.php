<?php

namespace Domains\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Право в системе (для админов сайта и для гильдий).
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'scope',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
