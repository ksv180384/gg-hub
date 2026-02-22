<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;

class DeletePermissionAction
{
    public function __invoke(Permission $permission): void
    {
        $permission->delete();
    }
}
