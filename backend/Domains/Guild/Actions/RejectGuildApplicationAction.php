<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Validation\ValidationException;

class RejectGuildApplicationAction
{
    public function __invoke(User $reviewer, Guild $guild, GuildApplication $application): GuildApplication
    {
        if ($application->guild_id !== $guild->id) {
            throw ValidationException::withMessages(['application' => ['Заявка не принадлежит этой гильдии.']]);
        }

        if ($application->status !== 'pending') {
            throw ValidationException::withMessages(['application' => ['Заявку уже рассмотрели.']]);
        }

        $application->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        return $application->fresh();
    }
}
