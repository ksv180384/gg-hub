<?php

namespace Domains\Raid\Actions;

use Domains\Guild\Models\Guild;
use Domains\Raid\Models\Raid;
use Domains\User\Models\User;

class GetRaidAction
{
    public function __invoke(Guild $guild, int $raidId, ?User $user = null): ?Raid
    {
        $query = Raid::query()
            ->where('guild_id', $guild->id)
            ->where('id', $raidId)
            ->with(['leader:id,name,user_id', 'parent:id,name,parent_id', 'creator:id,name', 'members:id,name'])
            ->withCount([
                'applications as pending_applications_count' => fn ($query) => $query->where('status', 'pending'),
            ]);

        if ($user) {
            $query->with([
                'applications' => fn ($query) => $query->whereHas(
                    'character',
                    fn ($characterQuery) => $characterQuery->where('user_id', $user->id),
                ),
            ]);
        }

        return $query->first();
    }
}
