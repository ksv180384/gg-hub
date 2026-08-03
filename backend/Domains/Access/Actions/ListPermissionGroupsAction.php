<?php

namespace Domains\Access\Actions;

use App\Filters\PermissionScopeFilter;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Collection;

class ListPermissionGroupsAction
{
    /**
     * @return Collection<int, PermissionGroup>
     */
    public function __invoke(PermissionScopeFilter|PermissionScope|null $filter = null): Collection
    {
        $query = PermissionGroup::query()->with('permissions');

        if ($filter instanceof PermissionScopeFilter) {
            $query->filter($filter);
        } elseif ($filter instanceof PermissionScope) {
            $query->where('scope', $filter);
        }

        return $query->orderBy('name')->get();
    }
}
