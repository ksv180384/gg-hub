<?php

namespace Domains\Access\Actions;

use App\Models\User;
use Carbon\Carbon;

class UpdateUserBanAction
{
    public function execute(User $user, bool $banned): User
    {
        $user->banned_at = $banned ? Carbon::now() : null;
        $user->save();
        return $user->fresh();
    }
}
