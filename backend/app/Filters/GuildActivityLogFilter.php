<?php

declare(strict_types=1);

namespace App\Filters;

use App\Core\Filters\Filter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class GuildActivityLogFilter extends Filter
{
    public const KEYS_TO_DATE = [
        'created_from',
        'created_to',
    ];

    protected function createdFrom(CarbonImmutable $value): Builder
    {
        return $this->builder->where('created_at', '>=', $value->startOfDay());
    }

    protected function createdTo(CarbonImmutable $value): Builder
    {
        return $this->builder->where('created_at', '<=', $value->endOfDay());
    }

    protected function category(string $value): Builder
    {
        return $this->builder->where('category', $value);
    }

    protected function action(string $value): Builder
    {
        return $this->builder->where('action', $value);
    }

    protected function actorName(string $value): Builder
    {
        $search = trim($value);

        if ($search === '') {
            return $this->builder;
        }

        return $this->builder->where(function (Builder $query) use ($search): void {
            $query
                ->where('actor_name', 'like', '%'.$search.'%')
                ->orWhereHas('actor', fn (Builder $actorQuery) => $actorQuery->where('name', 'like', '%'.$search.'%'));
        });
    }

    protected function search(string $value): Builder
    {
        $search = trim($value);

        if ($search === '') {
            return $this->builder;
        }

        return $this->builder->where(function (Builder $query) use ($search): void {
            $query
                ->where('description', 'like', '%'.$search.'%')
                ->orWhere('subject_name', 'like', '%'.$search.'%');
        });
    }
}
