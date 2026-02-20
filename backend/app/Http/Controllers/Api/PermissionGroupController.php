<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StorePermissionGroupRequest;
use App\Http\Requests\Access\UpdatePermissionGroupRequest;
use App\Http\Resources\Access\PermissionGroupResource;
use Domains\Access\Actions\CreatePermissionGroupAction;
use Domains\Access\Actions\ListPermissionGroupsAction;
use Domains\Access\Actions\UpdatePermissionGroupAction;
use Domains\Access\Models\PermissionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionGroupController extends Controller
{
    public function __construct(
        private ListPermissionGroupsAction $listPermissionGroupsAction,
        private CreatePermissionGroupAction $createPermissionGroupAction,
        private UpdatePermissionGroupAction $updatePermissionGroupAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $groups = $this->listPermissionGroupsAction->execute();
        return PermissionGroupResource::collection($groups);
    }

    public function show(PermissionGroup $permissionGroup): PermissionGroupResource
    {
        return new PermissionGroupResource($permissionGroup);
    }

    public function store(StorePermissionGroupRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array('obshhie-roli', $user->getAllPermissionSlugs(), true)) {
            abort(403, 'Недостаточно прав для создания категорий прав.');
        }
        $group = $this->createPermissionGroupAction->execute($request->validated());
        return (new PermissionGroupResource($group))->response()->setStatusCode(201);
    }

    public function update(UpdatePermissionGroupRequest $request, PermissionGroup $permissionGroup): PermissionGroupResource
    {
        $user = $request->user();
        if (!$user || !in_array('obshhie-roli', $user->getAllPermissionSlugs(), true)) {
            abort(403, 'Недостаточно прав для редактирования категорий прав.');
        }
        $this->updatePermissionGroupAction->execute($permissionGroup, $request->validated());
        return new PermissionGroupResource($permissionGroup);
    }
}
