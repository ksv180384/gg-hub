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
        $user = ($this->updateUserRolesPermissionsAction)($user, $request->validated());
        return (new UserRolesPermissionsResource($user))->response();
    }
}
