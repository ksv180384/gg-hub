<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

/**
 * Отзывает приглашение в гильдию. Вызывать может только участник гильдии с правом
 * «Подтверждение или отклонение заявок»; в приглашении сохраняется персонаж отзвавшего.
 */
class RevokeGuildInvitationAction
{
    public function __invoke(User $revoker, Guild $guild, GuildApplication $application): GuildApplication
    {
        if ($application->guild_id !== $guild->id) {
            throw ValidationException::withMessages(['application' => ['Приглашение не принадлежит этой гильдии.']]);
        }

        if ($application->status !== 'invitation') {
            throw ValidationException::withMessages(['application' => ['Отозвать можно только приглашение в статусе «приглашение».']]);
        }

        $revokerCharacterId = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $revoker->id))
            ->value('character_id');

        if (!$revokerCharacterId) {
            throw ValidationException::withMessages(['application' => ['У вас нет персонажа в этой гильдии.']]);
        }

        $application->update([
            'status' => 'revoked',
            'revoked_by_character_id' => $revokerCharacterId,
            'reviewed_at' => now(),
            'reviewed_by' => $revoker->id,
        ]);

        return $application->fresh();
    }
}
