<?php

namespace Domains\Access\Actions;

use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Collection;

class ListPermissionGroupsAction
{
    /**
     * @param PermissionScope|null $scope Если передан — только группы с этим scope (site/guild).
     * @return Collection<int, PermissionGroup>
     */
    public function __invoke(?PermissionScope $scope = null): Collection
    {
        $query = PermissionGroup::with('permissions')->orderBy('name');
        if ($scope !== null) {
            $query->where('scope', $scope);
        }
        return $query->get();
    }
}
