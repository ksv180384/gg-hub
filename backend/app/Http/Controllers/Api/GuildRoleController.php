<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\StoreGuildRoleRequest;
use App\Http\Requests\Guild\UpdateGuildRolePermissionsRequest;
use App\Http\Resources\Access\GuildRoleResource;
use App\Http\Resources\Access\PermissionGroupResource;
use Domains\Access\Actions\CreateGuildRoleAction;
use Domains\Access\Actions\DeleteGuildRoleAction;
use Domains\Access\Actions\ListGuildRolesAction;
use Domains\Access\Actions\ListPermissionGroupsAction;
use Domains\Access\Actions\UpdateGuildRolePermissionsAction;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\GuildRole;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildRoleController extends Controller
{
    public function __construct(
        private ListGuildRolesAction $listGuildRolesAction,
        private CreateGuildRoleAction $createGuildRoleAction,
        private UpdateGuildRolePermissionsAction $updateGuildRolePermissionsAction,
        private DeleteGuildRoleAction $deleteGuildRoleAction,
        private ListPermissionGroupsAction $listPermissionGroupsAction,
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}

    /** Группы прав гильдии для назначения ролям (доступно участникам гильдии). */
    public function permissionGroups(Guild $guild): AnonymousResourceCollection
    {
        $groups = ($this->listPermissionGroupsAction)(PermissionScope::Guild);
        return PermissionGroupResource::collection($groups);
    }

    public function index(Request $request, Guild $guild): JsonResponse
    {
        $roles = ($this->listGuildRolesAction)($guild);
        $user = $request->user();
        $myPermissionSlugs = $user ? ($this->getUserGuildPermissionSlugsAction)($user, $guild)->all() : [];

        return (new AnonymousResourceCollection($roles, GuildRoleResource::class))
            ->additional(['my_permission_slugs' => $myPermissionSlugs])
            ->response();
    }

    public function store(StoreGuildRoleRequest $request, Guild $guild): JsonResponse
    {
        $role = ($this->createGuildRoleAction)($guild, $request->validated());
        $role->load('permissions');
        return (new GuildRoleResource($role))->response()->setStatusCode(201);
    }

    public function updatePermissions(UpdateGuildRolePermissionsRequest $request, Guild $guild, GuildRole $guildRole): GuildRoleResource
    {
        if ((int) $guildRole->guild_id !== (int) $guild->id) {
            abort(404);
        }
        ($this->updateGuildRolePermissionsAction)($guildRole, $request->validated()['permission_ids']);
        $guildRole->load('permissions');
        return new GuildRoleResource($guildRole);
    }

    public function destroy(Guild $guild, GuildRole $guildRole): JsonResponse
    {
        if ((int) $guildRole->guild_id !== (int) $guild->id) {
            abort(404);
        }
        ($this->deleteGuildRoleAction)($guildRole);
        return response()->json(['message' => 'Роль удалена.']);
    }
}
