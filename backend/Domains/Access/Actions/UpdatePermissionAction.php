<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;

class UpdatePermissionAction
{
    /**
     * @param array{name?: string, slug?: string|null, description?: string|null, permission_group_id?: int} $data
     */
    public function __invoke(Permission $permission, array $data): Permission
    {
        if (isset($data['permission_group_id'])) {
            $group = PermissionGroup::findOrFail($data['permission_group_id']);
            $data['scope'] = $group->scope;
        }
        $permission->update($data);
        $permission->load('group');
        return $permission;
    }
}
