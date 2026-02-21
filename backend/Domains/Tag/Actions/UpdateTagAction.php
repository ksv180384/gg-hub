<?php

namespace Domains\Tag\Actions;

use Domains\Tag\Models\Tag;

class UpdateTagAction
{
    /**
     * @param array{name?: string, slug?: string|null, is_hidden?: bool} $data
     */
    public function __invoke(Tag $tag, array $data): Tag
    {
        $tag->update($data);
        return $tag->fresh();
    }
}
