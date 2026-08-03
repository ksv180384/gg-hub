<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class EventHistoryTitleFilter extends Filter
{
    protected function query(string $value): Builder
    {
        $value = mb_strtolower(trim($value));

        return $value === ''
            ? $this->builder
            : $this->builder->whereRaw('LOWER(name) LIKE ?', ["%{$value}%"]);
    }
}
