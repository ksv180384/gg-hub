<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Domains\Access\Actions\ListUsersAction;
use Domains\Access\Actions\UpdateUserBanAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function __construct(
        private ListUsersAction $listUsersAction,
        private UpdateUserBanAction $updateUserBanAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $users = $this->listUsersAction->execute();
        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $user->load('roles', 'directPermissions');
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser || !in_array('zablokirovat-polzovatelia', $currentUser->getAllPermissionSlugs(), true)) {
            abort(403, 'Недостаточно прав для блокировки пользователей.');
        }
        $user = $this->updateUserBanAction->execute($user, $request->validated()['banned']);
        return (new UserResource($user))->response();
    }
}
