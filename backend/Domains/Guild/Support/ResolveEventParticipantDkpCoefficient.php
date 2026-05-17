<?php

namespace Domains\Guild\Support;

use Domains\Guild\Models\GuildMember;

final class ResolveEventParticipantDkpCoefficient
{
    /**
     * @param  array{character_id?: int|null, dkp_coefficient?: float|int|string|null}  $participant
     */
    public function __invoke(int $guildId, array $participant): float
    {
        if (array_key_exists('dkp_coefficient', $participant) && $participant['dkp_coefficient'] !== null && $participant['dkp_coefficient'] !== '') {
            return (float) $participant['dkp_coefficient'];
        }

        $characterId = $participant['character_id'] ?? null;
        if ($characterId) {
            $coefficient = GuildMember::query()
                ->where('guild_id', $guildId)
                ->where('character_id', $characterId)
                ->value('dkp_coefficient');

            if ($coefficient !== null) {
                return (float) $coefficient;
            }
        }

        return 1.0;
    }
}
