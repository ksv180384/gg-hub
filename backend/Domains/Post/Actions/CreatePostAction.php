<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\Post;

class CreatePostAction
{
    public function execute(array $data): Post
    {
        return Post::create($data);
    }
}
