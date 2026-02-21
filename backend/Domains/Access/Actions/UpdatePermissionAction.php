<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;

class UpdatePermissionAction
{
    /**
     * @param array{name?: string, slug?: string|null, description?: string|null, permission_group_id?: int} $data
     */
    public function __invoke(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        $permission->load('group');
        return $permission;
    }
}
