<?php

declare(strict_types=1);

namespace Domains\Access\Actions;

use App\Actions\GuildActivity\RecordGuildActivityAction;
use App\GuildActivityLog;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\GuildRole;
use Domains\Access\Models\Permission;
use Domains\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateGuildRolePermissionsAction
{
    public function __construct(
        private RecordGuildActivityAction $recordGuildActivityAction,
    ) {}

    /**
     * Синхронизирует права гильдии у роли. Принимаются только права с scope guild.
     *
     * @param array<int> $permissionIds
     */
    public function __invoke(GuildRole $role, array $permissionIds): void
    {
        DB::transaction(function () use ($role, $permissionIds): void {
            $previousPermissions = $role->permissions()
                ->orderBy('permissions.id')
                ->get(['permissions.id', 'permissions.name']);

            $validPermissions = Permission::query()
                ->where('scope', PermissionScope::Guild)
                ->whereIn('id', $permissionIds)
                ->orderBy('id')
                ->get(['id', 'name']);

            $role->permissions()->sync($validPermissions->modelKeys());

            $actor = Auth::user();
            $actor = $actor instanceof User ? $actor : null;

            ($this->recordGuildActivityAction)(
                (int) $role->guild_id,
                $actor,
                GuildActivityLog::CATEGORY_ACCESS,
                'access.role_permissions_updated',
                "Изменены права роли «{$role->name}».",
                $role,
                $role->name,
                ['permissions' => $previousPermissions->pluck('name')->all()],
                ['permissions' => $validPermissions->pluck('name')->all()],
            );
        });
    }
}
