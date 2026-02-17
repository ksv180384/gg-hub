<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class ListPermissionsAction
{
    /**
     * @return Collection<int, Permission>
     */
    public function execute(): Collection
    {
        return Permission::with('group')->orderBy('permission_group_id')->orderBy('name')->get();
    }
}
