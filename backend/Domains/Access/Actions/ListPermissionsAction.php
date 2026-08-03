<?php

namespace Domains\Access\Actions;

use App\Filters\PermissionScopeFilter;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class ListPermissionsAction
{
    /**
     * @return Collection<int, Permission>
     */
    public function __invoke(PermissionScopeFilter|PermissionScope|null $filter = null): Collection
    {
        $query = Permission::query()->with('group');

        if ($filter instanceof PermissionScopeFilter) {
            $query->filter($filter);
        } elseif ($filter instanceof PermissionScope) {
            $query->where('scope', $filter);
        }

        return $query
            ->orderBy('permission_group_id')
            ->orderBy('name')
            ->get();
    }
}
