<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;

class GetPermissionAction
{
    public function __invoke(Permission $permission): Permission
    {
        $permission->load('group');
        return $permission;
    }
}
