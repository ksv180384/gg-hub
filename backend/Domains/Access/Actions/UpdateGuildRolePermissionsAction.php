<?php

namespace Domains\Access\Actions;

use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\GuildRole;
use Domains\Access\Models\Permission;

class UpdateGuildRolePermissionsAction
{
    /**
     * Синхронизирует права гильдии у роли. Принимаются только права с scope guild.
     *
     * @param array<int> $permissionIds
     */
    public function __invoke(GuildRole $role, array $permissionIds): void
    {
        $validIds = Permission::query()
            ->where('scope', PermissionScope::Guild)
            ->whereIn('id', $permissionIds)
            ->pluck('id')
            ->all();
        $role->permissions()->sync($validIds);
    }
}
