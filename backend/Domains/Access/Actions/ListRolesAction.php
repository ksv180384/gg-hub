<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class ListRolesAction
{
    /**
     * @return Collection<int, Role>
     */
    public function __invoke(): Collection
    {
        return Role::with('permissions')->orderBy('name')->get();
    }
}
