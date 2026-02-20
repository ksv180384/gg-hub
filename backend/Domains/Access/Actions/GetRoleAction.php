<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Role;

class GetRoleAction
{
    public function execute(Role $role): Role
    {
        $role->load('permissions');
        return $role;
    }
}
