<?php

namespace Domains\Access\Actions;

use App\Models\User;

class GetUserAction
{
    public function __invoke(User $user): User
    {
        $user->load([
            'roles',
            'directPermissions',
            'characters' => fn ($q) => $q->with(['game', 'server', 'guildMember.guild']),
        ]);
        return $user;
    }
}
