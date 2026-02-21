<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\GetCurrentUserAction;
use App\Actions\User\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private GetCurrentUserAction $getCurrentUserAction,
        private UpdateUserProfileAction $updateUserProfileAction
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = ($this->getCurrentUserAction)($request->user());
        return response()->json(['user' => $user ? (new UserResource($user))->resolve() : null]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user = ($this->updateUserProfileAction)(
            $user,
            $request->validated(),
            $request->file('avatar')
        );

        return response()->json(['user' => (new UserResource($user))->resolve()]);
    }
}
