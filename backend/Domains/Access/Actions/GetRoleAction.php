<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Role;

class GetRoleAction
{
    public function __invoke(Role $role): Role
    {
        $role->load('permissions');
        return $role;
    }
}
