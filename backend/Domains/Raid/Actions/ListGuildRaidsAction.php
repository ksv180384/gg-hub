<?php

namespace Domains\Raid\Actions;

use Domains\Guild\Models\Guild;
use Domains\Raid\Models\Raid;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class ListGuildRaidsAction
{
    /**
     * Рейды гильдии в виде дерева (корневые с вложенными children любой глубины).
     *
     * @return Collection<int, Raid>
     */
    public function __invoke(Guild $guild, ?User $user = null): Collection
    {
        $query = Raid::query()
            ->where('guild_id', $guild->id)
            ->with('leader:id,name,user_id')
            ->withCount('members')
            ->withCount([
                'applications as pending_applications_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($user) {
            $query->with([
                'applications' => fn ($query) => $query->whereHas(
                    'character',
                    fn ($characterQuery) => $characterQuery->where('user_id', $user->id),
                ),
            ]);
        }

        $all = $query->get();

        $byParent = $all->groupBy('parent_id');
        $all->each(function (Raid $raid) use ($byParent) {
            $raid->setRelation('children', $byParent->get($raid->id, new BaseCollection)->values());
        });

        $roots = $byParent->get(null, new BaseCollection)->values();

        return new Collection($roots->all());
    }
}
