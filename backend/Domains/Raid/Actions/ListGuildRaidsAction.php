<?php

namespace Domains\Raid\Actions;

use Domains\Guild\Models\Guild;
use Domains\Raid\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class ListGuildRaidsAction
{
    /**
     * Рейды гильдии в виде дерева (корневые с вложенными children любой глубины).
     *
     * @return Collection<int, Raid>
     */
    public function __invoke(Guild $guild): Collection
    {
        $all = Raid::query()
            ->where('guild_id', $guild->id)
            ->with('leader:id,name')
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $all->groupBy('parent_id');
        $all->each(function (Raid $raid) use ($byParent) {
            $raid->setRelation('children', $byParent->get($raid->id, new BaseCollection())->values());
        });

        $roots = $byParent->get(null, new BaseCollection())->values();

        return new Collection($roots->all());
    }
}
