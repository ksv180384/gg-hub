<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

class GetUserGuildsForGameAction
{
    private const ROLES_PAGE_PERMISSION_SLUGS = [
        'dobavliat-rol',
        'meniat-izieniat-polzovateliu-rol',
        'izmeniat-prava-roli',
        'udaliat-rol',
    ];

    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}

    /**
     * Гильдии текущей игры, в которых состоит пользователь (хотя бы один его персонаж в гильдии).
     * Для каждой гильдии: is_leader, can_access_roles (доступ к странице «Роли членов гильдии»).
     *
     * @return Collection<int, array{id: int, name: string, is_leader: bool, can_access_roles: bool}>
     */
    public function __invoke(User $user, int $gameId): Collection
    {
        $userCharacterIds = Character::query()
            ->where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->pluck('id');

        if ($userCharacterIds->isEmpty()) {
            return collect();
        }

        $guilds = Guild::query()
            ->where('game_id', $gameId)
            ->whereHas('members', function ($q) use ($userCharacterIds) {
                $q->whereIn('character_id', $userCharacterIds);
            })
            ->with('leader')
            ->orderBy('name')
            ->get();

        return $guilds->map(function (Guild $guild) use ($user) {
            $permissionSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);
            $canAccessRoles = $permissionSlugs->contains(fn (string $slug): bool => in_array($slug, self::ROLES_PAGE_PERMISSION_SLUGS, true));

            return [
                'id' => $guild->id,
                'name' => $guild->name,
                'is_leader' => $guild->leader_character_id && $guild->leader &&
                    (int) $guild->leader->user_id === (int) $user->id,
                'can_access_roles' => $canAccessRoles,
            ];
        });
    }
}
