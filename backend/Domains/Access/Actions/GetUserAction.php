<?php

namespace Domains\Access\Actions;

use App\Models\User;

class GetUserAction
{
    public function __invoke(User $user): User
    {
        $user->load('roles', 'directPermissions');
        return $user;
    }
}
