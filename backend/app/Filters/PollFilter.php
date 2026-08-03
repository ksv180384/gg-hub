<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class PollFilter extends Filter
{
    public const KEYS_TO_INT = ['guild_id'];

    protected function guildId(int $value): Builder
    {
        return $this->builder->where('guild_id', $value);
    }
}
