<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

class ApproveGuildApplicationAction
{
    public function __invoke(User $reviewer, Guild $guild, GuildApplication $application): GuildApplication
    {
        if ($application->guild_id !== $guild->id) {
            throw ValidationException::withMessages(['application' => ['Заявка не принадлежит этой гильдии.']]);
        }

        $application->loadMissing('character');
        if ($application->status === 'invitation') {
            if ((int) $application->character?->user_id !== (int) $reviewer->id) {
                throw ValidationException::withMessages(['application' => ['Принять приглашение может только владелец персонажа.']]);
            }
        } elseif ($application->status === 'pending') {
            // Участник гильдии с правом одобряет заявку — проверка в middleware
        } else {
            throw ValidationException::withMessages(['application' => ['Заявку уже рассмотрели.']]);
        }

        $characterId = $application->character_id;
        if (GuildMember::query()->where('character_id', $characterId)->exists()) {
            throw ValidationException::withMessages(['application' => ['Этот персонаж уже состоит в гильдии.']]);
        }

        $defaultRoleId = $guild->roles()->where('slug', 'novice')->value('id')
            ?? $guild->roles()->where('slug', '!=', 'leader')->orderBy('priority')->value('id');

        GuildMember::create([
            'guild_id' => $guild->id,
            'character_id' => $characterId,
            'guild_role_id' => $defaultRoleId,
            'joined_at' => now(),
        ]);

        $application->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        return $application->fresh();
    }
}
