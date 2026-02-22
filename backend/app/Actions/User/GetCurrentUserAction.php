<?php

namespace App\Actions\User;

use App\Models\User;

class GetCurrentUserAction
{
    public function __invoke(?User $user): ?User
    {
        if ($user === null) {
            return null;
        }
        $user->load('roles', 'directPermissions');
        return $user;
    }
}
