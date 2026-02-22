<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\GuildRole;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Str;

class CreateGuildRoleAction
{
    /**
     * @param array{name: string, slug?: string|null, priority?: int} $data
     */
    public function __invoke(Guild $guild, array $data): GuildRole
    {
        $data['guild_id'] = $guild->id;
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $data['priority'] = $data['priority'] ?? 0;
        return GuildRole::create($data);
    }
}
