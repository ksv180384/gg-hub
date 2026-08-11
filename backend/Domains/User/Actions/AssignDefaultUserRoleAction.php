<?php

namespace Domains\User\Actions;

use Domains\Access\Models\Role;
use Domains\User\Models\User;

class AssignDefaultUserRoleAction
{
    public const DEFAULT_ROLE_SLUG = 'polzovatel';

    public function __invoke(User $user): void
    {
        $defaultRole = Role::query()
            ->where('slug', self::DEFAULT_ROLE_SLUG)
            ->first();

        if ($defaultRole) {
            $user->roles()->syncWithoutDetaching([$defaultRole->id]);
        }
    }
}
