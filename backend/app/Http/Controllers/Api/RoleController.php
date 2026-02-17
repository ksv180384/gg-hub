<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreRoleRequest;
use App\Http\Requests\Access\UpdateRoleRequest;
use App\Http\Resources\Access\RoleResource;
use Domains\Access\Actions\CreateRoleAction;
use Domains\Access\Actions\GetRoleAction;
use Domains\Access\Actions\ListRolesAction;
use Domains\Access\Actions\UpdateRoleAction;
use Domains\Access\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(
        private ListRolesAction $listRolesAction,
        private GetRoleAction $getRoleAction,
        private CreateRoleAction $createRoleAction,
        private UpdateRoleAction $updateRoleAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $roles = $this->listRolesAction->execute();
        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        $role = $this->getRoleAction->execute($role);
        return new RoleResource($role);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->createRoleAction->execute($request->validated());
        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role = $this->updateRoleAction->execute($role, $request->validated());
        return new RoleResource($role);
    }
}
