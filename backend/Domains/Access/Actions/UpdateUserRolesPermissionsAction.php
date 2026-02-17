<?php

namespace Domains\Access\Actions;

use App\Models\User;

class UpdateUserRolesPermissionsAction
{
    /**
     * @param array{role_ids?: array<int>, permission_ids?: array<int>} $data
     */
    public function execute(User $user, array $data): User
    {
        if (array_key_exists('role_ids', $data)) {
            $user->roles()->sync($data['role_ids']);
        }
        if (array_key_exists('permission_ids', $data)) {
            $user->directPermissions()->sync($data['permission_ids']);
        }
        $user->load('roles', 'directPermissions');
        return $user;
    }
}
