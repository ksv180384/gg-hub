<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\GuildApplicationFormField;

class DeleteGuildApplicationFormFieldAction
{
    public function __invoke(GuildApplicationFormField $field): void
    {
        $field->delete();
    }
}
