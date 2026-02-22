<?php

namespace Domains\Access\Actions;

use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\PermissionGroup;

class CreatePermissionGroupAction
{
    /**
     * @param array{scope?: PermissionScope|string, name: string, slug?: string|null} $data
     */
    public function __invoke(array $data): PermissionGroup
    {
        $data['scope'] = $data['scope'] ?? 'site';
        return PermissionGroup::create($data);
    }
}
