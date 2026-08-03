<?php

namespace Domains\Raid\Actions;

use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Raid\Models\Raid;
use Domains\User\Models\User;

class CanManageRaidRecruitmentAction
{
    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction,
    ) {}

    public function __invoke(User $user, Raid $raid): bool
    {
        $raid->loadMissing(['guild', 'leader']);

        if ($raid->leader && (int) $raid->leader->user_id === (int) $user->id) {
            return true;
        }

        return ($this->getUserGuildPermissionSlugsAction)($user, $raid->guild)
            ->contains('formirovat-reidy');
    }
}
