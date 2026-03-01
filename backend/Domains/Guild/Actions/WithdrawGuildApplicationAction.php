<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Validation\ValidationException;

class WithdrawGuildApplicationAction
{
    /**
     * Отзыв заявки пользователем, который её подал (только в статусе «на рассмотрении»).
     */
    public function __invoke(User $user, Guild $guild, GuildApplication $application): GuildApplication
    {
        if ($application->guild_id !== $guild->id) {
            throw ValidationException::withMessages(['application' => ['Заявка не найдена.']]);
        }

        if ($application->status !== 'pending') {
            throw ValidationException::withMessages(['application' => ['Отозвать можно только заявку на рассмотрении.']]);
        }

        $application->loadMissing('character');
        if (!$application->character || (int) $application->character->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['application' => ['Заявка не найдена.']]);
        }

        $application->update([
            'status' => 'withdrawn',
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
        ]);

        return $application->fresh();
    }
}
