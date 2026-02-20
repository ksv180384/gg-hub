<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UpdateUserProfileAction $updateUserProfileAction
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['user' => null]);
        }
        $user->load('roles', 'directPermissions');
        return response()->json(['user' => (new UserResource($user))->resolve()]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user = $this->updateUserProfileAction->execute(
            $user,
            $request->validated(),
            $request->file('avatar')
        );

        return response()->json(['user' => (new UserResource($user))->resolve()]);
    }
}
