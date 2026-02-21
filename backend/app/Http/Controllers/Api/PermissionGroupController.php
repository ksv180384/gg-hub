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
        $groups = ($this->listPermissionGroupsAction)();
        return PermissionGroupResource::collection($groups);
    }

    public function show(PermissionGroup $permissionGroup): PermissionGroupResource
    {
        return new PermissionGroupResource($permissionGroup);
    }

    public function store(StorePermissionGroupRequest $request): JsonResponse
    {
        $group = ($this->createPermissionGroupAction)($request->validated());
        return (new PermissionGroupResource($group))->response()->setStatusCode(201);
    }

    public function update(UpdatePermissionGroupRequest $request, PermissionGroup $permissionGroup): PermissionGroupResource
    {
        ($this->updatePermissionGroupAction)($permissionGroup, $request->validated());
        return new PermissionGroupResource($permissionGroup);
    }
}
