<?php

namespace Domains\Access\Actions;

use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class ListPermissionsAction
{
    /**
     * @param PermissionScope|null $scope Если передан — только права с этим scope (site/guild).
     * @return Collection<int, Permission>
     */
    public function __invoke(?PermissionScope $scope = null): Collection
    {
        $query = Permission::with('group')->orderBy('permission_group_id')->orderBy('name');
        if ($scope !== null) {
            $query->where('scope', $scope);
        }
        return $query->get();
    }
}
