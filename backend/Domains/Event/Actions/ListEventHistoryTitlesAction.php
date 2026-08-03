<?php

namespace Domains\Event\Actions;

use App\Filters\EventHistoryTitleFilter;
use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Database\Eloquent\Collection;

class ListEventHistoryTitlesAction
{
    public function __invoke(EventHistoryTitleFilter $filter, int $limit = 10): Collection
    {
        return EventHistoryTitle::query()
            ->withCount('histories')
            ->filter($filter)
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
