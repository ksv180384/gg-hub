<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class EventFilter extends Filter
{
    protected function from(string $value): Builder
    {
        return $this->builder
            ->where(function (Builder $query) use ($value): void {
                $query->whereDate('ends_at', '>=', $value)->orWhereNull('ends_at');
            })
            ->where(function (Builder $query) use ($value): void {
                $query->whereDate('recurrence_ends_at', '>=', $value)->orWhereNull('recurrence_ends_at');
            });
    }

    protected function to(string $value): Builder
    {
        return $this->builder->whereDate('starts_at', '<=', $value);
    }
}
