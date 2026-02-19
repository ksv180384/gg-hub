<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdateUserRolesPermissionsRequest;
use App\Http\Resources\Access\UserRolesPermissionsResource;
use App\Models\User;
use Domains\Access\Actions\UpdateUserRolesPermissionsAction;
use Illuminate\Http\JsonResponse;

class UserRolePermissionController extends Controller
{
    public function __construct(
        private UpdateUserRolesPermissionsAction $updateUserRolesPermissionsAction
    ) {}

    public function update(UpdateUserRolesPermissionsRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $currentUser = $request->user();
        if (array_key_exists('role_ids', $data)) {
            if (!$currentUser || !in_array('izmeniat-rol-polzovatelia', $currentUser->getAllPermissionSlugs(), true)) {
                abort(403, 'Недостаточно прав для изменения роли пользователя.');
            }
        }
        if (array_key_exists('permission_ids', $data)) {
            if (!$currentUser || !in_array('izmeniat-prava-polzovatelia', $currentUser->getAllPermissionSlugs(), true)) {
                abort(403, 'Недостаточно прав для изменения прав пользователя.');
            }
        }
        $user = $this->updateUserRolesPermissionsAction->execute($user, $data);
        return (new UserRolesPermissionsResource($user))->response();
    }
}
