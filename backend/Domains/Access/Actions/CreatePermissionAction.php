<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;

class CreatePermissionAction
{
    /**
     * @param array{name: string, slug?: string|null, description?: string|null, permission_group_id: int} $data
     */
    public function execute(array $data): Permission
    {
        $permission = Permission::create($data);
        $permission->load('group');
        return $permission;
    }
}
