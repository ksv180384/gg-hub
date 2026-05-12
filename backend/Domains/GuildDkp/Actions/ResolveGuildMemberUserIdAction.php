<?php

namespace Domains\GuildDkp\Actions;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResolveGuildMemberUserIdAction
{
    public function __invoke(Guild $guild, int $characterId): int
    {
        $isMember = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->exists();

        if (! $isMember) {
            throw new HttpResponseException(response()->json([
                'message' => 'Участник гильдии не найден.',
                'errors' => [
                    'character_id' => ['Участник гильдии не найден.'],
                ],
            ], 422));
        }

        $character = Character::query()
            ->whereKey($characterId)
            ->first();

        if ($character === null || $character->user_id === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Участник гильдии не найден.',
                'errors' => [
                    'character_id' => ['Участник гильдии не найден.'],
                ],
            ], 422));
        }

        return (int) $character->user_id;
    }
}
