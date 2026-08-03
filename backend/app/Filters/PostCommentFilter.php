<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class PostCommentFilter extends Filter
{
    public const KEYS_TO_INT = ['post_id'];

    protected function postId(int $value): Builder
    {
        return $this->builder->where('post_id', $value);
    }
}
