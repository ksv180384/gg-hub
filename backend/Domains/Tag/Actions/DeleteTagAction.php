<?php

namespace Domains\Tag\Actions;

use Domains\Tag\Models\Tag;

class DeleteTagAction
{
    public function __invoke(Tag $tag): void
    {
        $tag->delete();
    }
}
