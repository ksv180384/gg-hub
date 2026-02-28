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
        $allowed = ['name', 'type', 'required', 'sort_order', 'options'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (array_key_exists('options', $update) && is_array($update['options'])) {
            $update['options'] = array_values(array_filter(array_map('trim', $update['options'])));
        }
        $field->update($update);
        return $field->fresh();
    }
}
