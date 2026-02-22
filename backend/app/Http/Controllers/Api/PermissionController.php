<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Access\StorePermissionRequest;
use App\Http\Requests\Access\UpdatePermissionRequest;
use App\Http\Resources\Access\PermissionResource;
use Domains\Access\Actions\CreatePermissionAction;
use Domains\Access\Actions\DeletePermissionAction;
use Domains\Access\Actions\GetPermissionAction;
use Domains\Access\Actions\ListPermissionsAction;
use Domains\Access\Actions\UpdatePermissionAction;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\QueryException;

class PermissionController extends Controller
{
    private const PERMISSION_SITE_MANAGE = 'obshhie-roli';
    private const PERMISSION_GUILD_ADD = 'dobavliat-pravo-gildii';
    private const PERMISSION_GUILD_EDIT = 'redaktirovat-pravo-gildii';
    private const PERMISSION_GUILD_DELETE = 'udaliat-pravo-gildii';

    public function __construct(
        private ListPermissionsAction $listPermissionsAction,
        private GetPermissionAction $getPermissionAction,
        private CreatePermissionAction $createPermissionAction,
        private UpdatePermissionAction $updatePermissionAction,
        private DeletePermissionAction $deletePermissionAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $scope = $request->query('scope');
        $scopeEnum = $scope && in_array($scope, ['site', 'guild'], true)
            ? PermissionScope::from($scope)
            : null;
        $permissions = ($this->listPermissionsAction)($scopeEnum);
        return PermissionResource::collection($permissions);
    }

    public function show(Permission $permission): PermissionResource
    {
        $permission = ($this->getPermissionAction)($permission);
        return new PermissionResource($permission);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }
        $validated = $request->validated();
        $group = PermissionGroup::find($validated['permission_group_id'] ?? 0);
        $scope = $group?->scope ?? PermissionScope::Site;
        $requiredSlug = $scope === PermissionScope::Guild ? self::PERMISSION_GUILD_ADD : self::PERMISSION_SITE_MANAGE;
        $slugs = $user->getAllPermissionSlugs();
        if (!in_array($requiredSlug, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для добавления этого права.'], 403);
        }

        try {
            $permission = ($this->createPermissionAction)($validated);
            return (new PermissionResource($permission))->response()->setStatusCode(201);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains((string) $e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'message' => 'Право с таким слагом уже существует в этой области (права пользователей или права гильдии). Укажите другое название или слаг.',
                    'errors' => [
                        'slug' => ['Право с таким слагом уже существует в этой области. Укажите другое название или слаг.'],
                    ],
                ], 422);
            }
            throw $e;
        }
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse|PermissionResource
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }
        $scope = $permission->scope ?? PermissionScope::Site;
        $requiredSlug = $scope === PermissionScope::Guild ? self::PERMISSION_GUILD_EDIT : self::PERMISSION_SITE_MANAGE;
        $slugs = $user->getAllPermissionSlugs();
        if (!in_array($requiredSlug, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для редактирования этого права.'], 403);
        }

        try {
            ($this->updatePermissionAction)($permission, $request->validated());
            $permission = ($this->getPermissionAction)($permission);
            return new PermissionResource($permission);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains((string) $e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'message' => 'Право с таким слагом уже существует в этой области. Укажите другое название или слаг.',
                    'errors' => [
                        'slug' => ['Право с таким слагом уже существует в этой области. Укажите другое название или слаг.'],
                    ],
                ], 422);
            }
            throw $e;
        }
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $user = request()->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }
        $scope = $permission->scope ?? PermissionScope::Site;
        $requiredSlug = $scope === PermissionScope::Guild ? self::PERMISSION_GUILD_DELETE : self::PERMISSION_SITE_MANAGE;
        $slugs = $user->getAllPermissionSlugs();
        if (!in_array($requiredSlug, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для удаления этого права.'], 403);
        }

        ($this->deletePermissionAction)($permission);
        return response()->json(null, 204);
    }
}
