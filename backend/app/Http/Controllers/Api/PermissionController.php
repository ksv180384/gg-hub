<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StorePermissionRequest;
use App\Http\Requests\Access\UpdatePermissionRequest;
use App\Http\Resources\Access\PermissionResource;
use Domains\Access\Actions\CreatePermissionAction;
use Domains\Access\Actions\ListPermissionsAction;
use Domains\Access\Actions\UpdatePermissionAction;
use Domains\Access\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    public function __construct(
        private ListPermissionsAction $listPermissionsAction,
        private CreatePermissionAction $createPermissionAction,
        private UpdatePermissionAction $updatePermissionAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $permissions = $this->listPermissionsAction->execute();
        return PermissionResource::collection($permissions);
    }

    public function show(Permission $permission): PermissionResource
    {
        $permission->load('group');
        return new PermissionResource($permission);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array('obshhie-roli', $user->getAllPermissionSlugs(), true)) {
            abort(403, 'Недостаточно прав для создания прав.');
        }
        $permission = $this->createPermissionAction->execute($request->validated());
        return (new PermissionResource($permission))->response()->setStatusCode(201);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionResource
    {
        $user = $request->user();
        if (!$user || !in_array('obshhie-roli', $user->getAllPermissionSlugs(), true)) {
            abort(403, 'Недостаточно прав для редактирования прав.');
        }
        $this->updatePermissionAction->execute($permission, $request->validated());
        return new PermissionResource($permission->load('group'));
    }
}
