<?php

namespace Domains\Tag\Actions;

use Domains\Tag\Models\Tag;

class CreateTagAction
{
    /**
     * @param array{name: string, slug?: string|null} $data
     */
    public function __invoke(array $data): Tag
    {
        return Tag::create($data);
    }
}
