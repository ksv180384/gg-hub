<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

/**
 * Обновляет коэффициент ДКП участника гильдии (по character_id).
 */
final class UpdateGuildMemberDkpCoefficientAction
{
    public function __invoke(Guild $guild, int $characterId, float $dkpCoefficient): GuildMember
    {
        $member = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'character_id' => ['Участник не найден в гильдии.'],
            ]);
        }

        $member->dkp_coefficient = $dkpCoefficient;
        $member->save();

        return $member->load(['character.gameClasses', 'guildRole']);
    }
}
