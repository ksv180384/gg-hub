<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Domains\Post\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;

class GuildPostFilter extends Filter
{
    public function apply(Builder $builder): Builder
    {
        if ($this->request->input('filter') === 'blocked') {
            return $builder
                ->where('status_guild', PostStatus::Blocked->value)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at');
        }

        return $builder
            ->where('status_guild', PostStatus::Published->value)
            ->whereNotNull('published_at_guild')
            ->orderByDesc('published_at_guild')
            ->orderByDesc('created_at');
    }
}
