<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Collection;

class ListPermissionGroupsAction
{
    /**
     * @return Collection<int, PermissionGroup>
     */
    public function __invoke(): Collection
    {
        return PermissionGroup::with('permissions')->orderBy('name')->get();
    }
}
