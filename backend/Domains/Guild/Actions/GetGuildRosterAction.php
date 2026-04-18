<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\Guild\GuildRosterMemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;

/**
 * Возвращает состав гильдии (персонажи с аватаркой, классами, ролью, тегами).
 * Доступ: если show_roster_to_all — любому авторизованному; иначе только участникам гильдии.
 */
final class GetGuildRosterAction
{
    public function __invoke(User $user, Guild $guild): AnonymousResourceCollection|JsonResponse
    {
        $isMember = $guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if (!$guild->show_roster_to_all && !$isMember) {
            return ResponseFacade::json([
                'message' => 'Состав гильдии доступен только участникам гильдии.',
            ], 403);
        }

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

        return GuildRosterMemberResource::collection($members);
    }
}
