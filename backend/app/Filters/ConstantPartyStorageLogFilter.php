<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ConstantPartyStorageLogFilter extends Filter
{
    protected function dateFrom(string $value): Builder
    {
        return $this->builder->whereDate('created_at', '>=', $value);
    }

    protected function dateTo(string $value): Builder
    {
        return $this->builder->whereDate('created_at', '<=', $value);
    }
}
