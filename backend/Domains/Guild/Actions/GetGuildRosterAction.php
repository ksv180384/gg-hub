<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\Guild\GuildRosterMemberResource;

/**
 * Возвращает состав гильдии (персонажи с аватаркой, классами, ролью, тегами).
 * Доступ только участникам гильдии (middleware guild.member).
 */
final class GetGuildRosterAction
{
    public function __invoke(Guild $guild): AnonymousResourceCollection
    {
        $members = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->with([
                'character.gameClasses',
                'character.tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'character.characterGuildTags' => fn ($q) => $q->notHidden()
                    ->where('character_guild_tag.guild_id', $guild->id)
                    ->with(['usedByUser', 'createdByUser']),
                'character.user',
                'guildRole',
            ])
            ->orderBy('joined_at')
            ->get();

        $guildRoles = $guild->roles()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return GuildRosterMemberResource::collection($members)->additional([
            'meta' => [
                'guild_roles' => $guildRoles->map(static fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])->values()->all(),
            ],
        ]);
    }
}
