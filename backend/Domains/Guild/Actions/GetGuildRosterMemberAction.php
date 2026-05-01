<?php

namespace Domains\Guild\Actions;

use App\Http\Resources\Guild\GuildRosterMemberResource;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Возвращает данные одного участника состава гильдии по character_id.
 * Доступ только участникам гильдии (middleware guild.member).
 */
final class GetGuildRosterMemberAction
{
    public function __invoke(Guild $guild, int $characterId): GuildRosterMemberResource
    {
        $member = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->with([
                'character.gameClasses',
                'character.tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'character.characterGuildTags' => fn ($q) => $q->notHidden()
                    ->where('character_guild_tag.guild_id', $guild->id)
                    ->with(['usedByUser', 'createdByUser']),
                'guildRole',
            ])
            ->first();

        if (! $member) {
            throw new NotFoundHttpException('Участник не найден в составе гильдии.');
        }

        return new GuildRosterMemberResource($member);
    }
}
