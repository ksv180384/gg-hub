<?php

namespace Domains\Access\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListUsersAction
{
    /**
     * @return Collection<int, User>
     */
    public function execute(): Collection
    {
        return User::with('roles', 'directPermissions')
            ->orderBy('name')
            ->get();
    }
}
