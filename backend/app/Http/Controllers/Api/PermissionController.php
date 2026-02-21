<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StorePermissionRequest;
use App\Http\Requests\Access\UpdatePermissionRequest;
use App\Http\Resources\Access\PermissionResource;
use Domains\Access\Actions\CreatePermissionAction;
use Domains\Access\Actions\GetPermissionAction;
use Domains\Access\Actions\ListPermissionsAction;
use Domains\Access\Actions\UpdatePermissionAction;
use Domains\Access\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    public function __construct(
        private ListPermissionsAction $listPermissionsAction,
        private GetPermissionAction $getPermissionAction,
        private CreatePermissionAction $createPermissionAction,
        private UpdatePermissionAction $updatePermissionAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $permissions = ($this->listPermissionsAction)();
        return PermissionResource::collection($permissions);
    }

    public function show(Permission $permission): PermissionResource
    {
        $permission = ($this->getPermissionAction)($permission);
        return new PermissionResource($permission);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = ($this->createPermissionAction)($request->validated());
        return (new PermissionResource($permission))->response()->setStatusCode(201);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionResource
    {
        ($this->updatePermissionAction)($permission, $request->validated());
        $permission = ($this->getPermissionAction)($permission);
        return new PermissionResource($permission);
    }
}
