<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\PermissionGroup;
use Illuminate\Validation\ValidationException;

class DeletePermissionGroupAction
{
    /**
     * Удаляет группу прав только если к ней не привязано ни одного права.
     *
     * @throws ValidationException Если в группе есть права.
     */
    public function __invoke(PermissionGroup $group): void
    {
        if ($group->permissions()->count() > 0) {
            throw ValidationException::withMessages([
                'permissions' => ['Невозможно удалить группу: к ней привязаны права. Сначала удалите или перенесите права.'],
            ]);
        }
        $group->delete();
    }
}
