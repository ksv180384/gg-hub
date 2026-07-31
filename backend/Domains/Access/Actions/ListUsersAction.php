<?php

namespace Domains\Access\Actions;

use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ListUsersAction
{
    /**
     * @return Collection<int, User>
     */
    public function __invoke(): Collection
    {
        return User::query()
            ->select('users.*')
            ->selectSub(
                DB::table('sessions')
                    ->selectRaw('MAX(last_activity)')
                    ->whereColumn('sessions.user_id', 'users.id'),
                'last_activity_at',
            )
            ->with('roles', 'directPermissions')
            ->orderBy('name')
            ->get();
    }
}
