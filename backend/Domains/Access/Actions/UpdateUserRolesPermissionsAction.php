<?php

namespace Domains\Access\Actions;

use App\Models\User;

class UpdateUserRolesPermissionsAction
{
    /**
     * @param array{role_ids?: array<int>, permission_ids?: array<int>} $data
     */
    public function __invoke(User $user, array $data): User
    {
        if (array_key_exists('role_ids', $data)) {
            $roleIds = $data['role_ids'];
            $user->roles()->sync(is_array($roleIds) && count($roleIds) > 0 ? [reset($roleIds)] : []);
        }
        if (array_key_exists('permission_ids', $data)) {
            $user->directPermissions()->sync($data['permission_ids']);
        }
        $user->load('roles', 'directPermissions');
        return $user;
    }
}
