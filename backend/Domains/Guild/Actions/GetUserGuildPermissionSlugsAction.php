<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Access\Actions\ListPermissionGroupsAction;
use Domains\Access\Enums\PermissionScope;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

class GetUserGuildPermissionSlugsAction
{
    public function __construct(
        private ListPermissionGroupsAction $listPermissionGroupsAction
    ) {}

    /**
     * Возвращает список slug прав, которые есть у пользователя в данной гильдии.
     * Лидер гильдии имеет все права гильдии. Остальные — по ролям своих персонажей.
     *
     * @return Collection<int, string>
     */
    public function __invoke(User $user, Guild $guild): Collection
    {
        $guild->loadMissing('leader');

        if ($guild->leader_character_id && $guild->leader && (int) $guild->leader->user_id === (int) $user->id) {
            return $this->getAllGuildPermissionSlugs();
        }

        $members = $guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->with('guildRole.permissions')
            ->get();

        $slugs = $members->flatMap(function ($member) {
            $role = $member->guildRole;
            if (!$role || !$role->relationLoaded('permissions')) {
                return [];
            }
            return $role->permissions->pluck('slug');
        })->unique()->values();

        return $slugs;
    }

    /**
     * Все slug прав гильдии (из групп с scope = guild). Если групп нет — лидер получает минимум прав для страницы ролей.
     *
     * @return Collection<int, string>
     */
    private function getAllGuildPermissionSlugs(): Collection
    {
        $groups = ($this->listPermissionGroupsAction)(PermissionScope::Guild);
        $slugs = $groups->flatMap(fn ($group) => $group->permissions->pluck('slug'))->unique()->values();

        if ($slugs->isEmpty()) {
            return collect([
                'dobavliat-rol',
                'meniat-izieniat-polzovateliu-rol',
                'izmeniat-prava-roli',
                'udaliat-rol',
            ]);
        }

        return $slugs;
    }
}
