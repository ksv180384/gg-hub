<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplicationFormField;

class CreateGuildApplicationFormFieldAction
{
    /**
     * @param array{name: string, type: string, required?: bool, sort_order?: int, options?: string[]} $data
     */
    public function __invoke(Guild $guild, array $data): GuildApplicationFormField
    {
        $sortOrder = $data['sort_order'] ?? (($guild->applicationFormFields()->max('sort_order') ?? -1) + 1);
        $payload = [
            'guild_id' => $guild->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'required' => $data['required'] ?? false,
            'sort_order' => $sortOrder,
        ];
        if (array_key_exists('options', $data) && is_array($data['options'])) {
            $payload['options'] = array_values(array_filter(array_map('trim', $data['options'])));
        }
        return GuildApplicationFormField::create($payload);
    }
}
