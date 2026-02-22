<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;

class CreatePermissionAction
{
    /**
     * @param array{name: string, slug?: string|null, description?: string|null, permission_group_id: int} $data
     */
    public function __invoke(array $data): Permission
    {
        $group = PermissionGroup::findOrFail($data['permission_group_id']);
        $data['scope'] = $group->scope;
        $permission = Permission::create($data);
        $permission->load('group');
        return $permission;
    }
}
