<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplicationVote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListGuildApplicationsAction
{
    public function __invoke(Guild $guild, int $perPage = 20, ?User $user = null): LengthAwarePaginator
    {
        $query = $guild->applications()
            ->with(['character', 'invitedByCharacter', 'revokedByCharacter'])
            ->withCount([
                'votes as likes_count' => fn ($q) => $q->where('vote', 1),
                'votes as dislikes_count' => fn ($q) => $q->where('vote', -1),
            ])
            ->orderByDesc('created_at')
        ;

        if ($user) {
            $query->addSelect([
                'my_vote' => GuildApplicationVote::query()
                    ->select('vote')
                    ->whereColumn('guild_application_id', 'guild_applications.id')
                    ->where('user_id', $user->id)
                    ->limit(1),
            ]);
        }

        return $query->paginate($perPage);
    }
}
