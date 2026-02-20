<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Role;

class CreateRoleAction
{
    /**
     * @param array{name: string, slug?: string|null, description?: string|null, permission_ids?: array<int>} $data
     */
    public function execute(array $data): Role
    {
        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);
        $role = Role::create($data);
        $role->permissions()->sync($permissionIds);
        $role->load('permissions');
        return $role;
    }
}
