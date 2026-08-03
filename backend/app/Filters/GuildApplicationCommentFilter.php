<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class GuildApplicationCommentFilter extends Filter
{
    public const KEYS_TO_INT = ['application_id'];

    protected function applicationId(int $value): Builder
    {
        return $this->builder->where('guild_application_id', $value);
    }
}
