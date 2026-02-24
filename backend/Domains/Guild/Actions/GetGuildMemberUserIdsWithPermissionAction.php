<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

/**
 * Возвращает user_id участников гильдии, у которых есть указанное право (по роли).
 * Лидер гильдии всегда имеет все права.
 */
class GetGuildMemberUserIdsWithPermissionAction
{
    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}

    /**
     * @return Collection<int, int> user_id
     */
    public function __invoke(Guild $guild, string $permissionSlug): Collection
    {
        $guild->loadMissing('leader');

        $userIds = collect();

        if ($guild->leader_character_id && $guild->leader) {
            $leaderUserSlugs = ($this->getUserGuildPermissionSlugsAction)($guild->leader->user, $guild);
            if ($leaderUserSlugs->contains($permissionSlug)) {
                $userIds->push((int) $guild->leader->user_id);
            }
        }

        $members = $guild->members()
            ->with('character.user', 'guildRole.permissions')
            ->get();

        foreach ($members as $member) {
            if (!$member->character) {
                continue;
            }
            $userId = (int) $member->character->user_id;
            if ($userIds->contains($userId)) {
                continue;
            }
            $role = $member->guildRole;
            if (!$role || !$role->relationLoaded('permissions')) {
                continue;
            }
            if ($role->permissions->contains('slug', $permissionSlug)) {
                $userIds->push($userId);
            }
        }

        return $userIds->unique()->values();
    }
}
