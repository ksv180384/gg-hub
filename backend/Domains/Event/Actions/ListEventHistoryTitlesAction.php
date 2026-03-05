<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Database\Eloquent\Collection;

class ListEventHistoryTitlesAction
{
    /**
     * @param  array{query?: string|null, limit?: int|null}  $params
     */
    public function __invoke(array $params = []): Collection
    {
        $query = EventHistoryTitle::query()->orderBy('name');

        if (! empty($params['query']) && is_string($params['query'])) {
            $q = mb_strtolower($params['query']);
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%']);
        }

        $limit = $params['limit'] ?? 10;

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }
}

