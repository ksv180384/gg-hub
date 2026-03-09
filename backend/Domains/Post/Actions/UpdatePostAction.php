<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\Post;

class UpdatePostAction
{
    public function __invoke(Post $post, array $data): Post
    {
        $post->fill($data);
        $post->save();

        return $post;
    }
}

