<?php

namespace Domains\Access\Actions;

use Carbon\Carbon;
use Domains\User\Models\User;

class UpdateUserBanAction
{
    public function __invoke(User $user, bool $banned): User
    {
        $user->banned_at = $banned ? Carbon::now() : null;
        $user->save();

        return $user->fresh();
    }
}
