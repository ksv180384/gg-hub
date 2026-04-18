<?php

namespace Domains\Guild\Actions;

use App\Http\Resources\Guild\GuildRosterMemberResource;
use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Возвращает данные одного участника состава гильдии по character_id.
 * Доступ: как у состава (show_roster_to_all или участник гильдии).
 */
final class GetGuildRosterMemberAction
{
    public function __invoke(User $user, Guild $guild, int $characterId): GuildRosterMemberResource|JsonResponse
    {
        $isMember = $guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if (! $guild->show_roster_to_all && ! $isMember) {
            return ResponseFacade::json([
                'message' => 'Состав гильдии доступен только участникам гильдии.',
            ], 403);
        }

        $member = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->with([
                'character.gameClasses',
                'character.tags' => fn ($q) => $q->with(['usedByUser', 'createdByUser']),
                'character.characterGuildTags' => fn ($q) => $q->where('character_guild_tag.guild_id', $guild->id)
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
