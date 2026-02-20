<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\PermissionGroup;

class CreatePermissionGroupAction
{
    /**
     * @param array{name: string, slug?: string|null} $data
     */
    public function execute(array $data): PermissionGroup
    {
        return PermissionGroup::create($data);
    }
}
