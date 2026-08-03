<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Domains\Access\Enums\PermissionScope;
use Illuminate\Database\Eloquent\Builder;

class PermissionScopeFilter extends Filter
{
    protected function scope(string $value): Builder
    {
        return in_array($value, ['site', 'guild'], true)
            ? $this->builder->where('scope', PermissionScope::from($value))
            : $this->builder;
    }
}
