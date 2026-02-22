<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StorePermissionGroupRequest;
use App\Http\Requests\Access\UpdatePermissionGroupRequest;
use App\Http\Resources\Access\PermissionGroupResource;
use Domains\Access\Actions\CreatePermissionGroupAction;
use Domains\Access\Actions\DeletePermissionGroupAction;
use Domains\Access\Actions\ListPermissionGroupsAction;
use Domains\Access\Actions\UpdatePermissionGroupAction;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\PermissionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PermissionGroupController extends Controller
{
    public function __construct(
        private ListPermissionGroupsAction $listPermissionGroupsAction,
        private CreatePermissionGroupAction $createPermissionGroupAction,
        private UpdatePermissionGroupAction $updatePermissionGroupAction,
        private DeletePermissionGroupAction $deletePermissionGroupAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $scope = $request->query('scope');
        $scopeEnum = $scope && in_array($scope, ['site', 'guild'], true)
            ? PermissionScope::from($scope)
            : null;
        $groups = ($this->listPermissionGroupsAction)($scopeEnum);
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

    public function destroy(PermissionGroup $permissionGroup): JsonResponse
    {
        try {
            ($this->deletePermissionGroupAction)($permissionGroup);
            return response()->json(null, 204);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Невозможно удалить группу.', 'errors' => $e->errors()], 422);
        }
    }
}
