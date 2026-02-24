<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\GuildApplicationFormField;

class UpdateGuildApplicationFormFieldAction
{
    /**
     * @param array{name?: string, type?: string, required?: bool, sort_order?: int} $data
     */
    public function __invoke(GuildApplicationFormField $field, array $data): GuildApplicationFormField
    {
        $allowed = ['name', 'type', 'required', 'sort_order'];
        $field->update(array_intersect_key($data, array_flip($allowed)));
        return $field->fresh();
    }
}
