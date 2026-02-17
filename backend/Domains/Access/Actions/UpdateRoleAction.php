<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Role;

class UpdateRoleAction
{
    /**
     * @param array{name?: string, slug?: string|null, description?: string|null, permission_ids?: array<int>} $data
     */
    public function execute(Role $role, array $data): Role
    {
        if (isset($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
            unset($data['permission_ids']);
        }
        $role->update($data);
        $role->load('permissions');
        return $role;
    }
}
