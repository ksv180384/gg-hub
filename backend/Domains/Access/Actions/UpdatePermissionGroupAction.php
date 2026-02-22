<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\PermissionGroup;

class UpdatePermissionGroupAction
{
    /**
     * @param array{name?: string, slug?: string|null} $data
     */
    public function __invoke(PermissionGroup $group, array $data): PermissionGroup
    {
        $group->update($data);
        return $group;
    }
}
