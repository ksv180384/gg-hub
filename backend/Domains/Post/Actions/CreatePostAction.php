<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\Post;

class CreatePostAction
{
    public function __invoke(array $data): Post
    {
        return Post::create($data);
    }
}
