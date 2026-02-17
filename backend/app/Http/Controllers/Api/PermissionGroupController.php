<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StorePermissionGroupRequest;
use App\Http\Resources\Access\PermissionGroupResource;
use Domains\Access\Actions\CreatePermissionGroupAction;
use Domains\Access\Actions\ListPermissionGroupsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionGroupController extends Controller
{
    public function __construct(
        private ListPermissionGroupsAction $listPermissionGroupsAction,
        private CreatePermissionGroupAction $createPermissionGroupAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $groups = $this->listPermissionGroupsAction->execute();
        return PermissionGroupResource::collection($groups);
    }

    public function store(StorePermissionGroupRequest $request): JsonResponse
    {
        $group = $this->createPermissionGroupAction->execute($request->validated());
        return (new PermissionGroupResource($group))->response()->setStatusCode(201);
    }
}
