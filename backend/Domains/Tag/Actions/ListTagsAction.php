<?php

namespace Domains\Tag\Actions;

use Domains\Tag\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class ListTagsAction
{
    /**
     * @return Collection<int, Tag>
     */
    public function __invoke(bool $includeHidden = true): Collection
    {
        $query = Tag::query()->with('createdBy')->orderBy('name');
        if (!$includeHidden) {
            $query->where('is_hidden', false);
        }
        return $query->get();
    }
}
