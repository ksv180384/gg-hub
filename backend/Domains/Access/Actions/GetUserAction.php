<?php

namespace Domains\Access\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetUserAction
{
    public function __invoke(User $user): User
    {
        $user->load([
            'roles',
            'directPermissions',
            'characters' => fn ($q) => $q->with(['game', 'server', 'guildMember.guild']),
        ]);
        $user->setAttribute(
            'last_activity_at',
            DB::table('sessions')->where('user_id', $user->id)->max('last_activity'),
        );
        return $user;
    }
}
