<?php

namespace Domains\Tag\Actions;

use App\Filters\TagFilter;
use Domains\Tag\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class ListTagsAction
{
    /**
     * @return Collection<int, Tag>
     */
    public function __invoke(TagFilter $filter): Collection
    {
        return Tag::query()
            ->with(['usedByUser', 'createdByUser', 'usedByGuild'])
            ->orderBy('name')
            ->filter($filter)
            ->get();
    }
}
