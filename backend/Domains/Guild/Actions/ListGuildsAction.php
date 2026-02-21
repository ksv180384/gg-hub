<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ListGuildsAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository
    ) {}

    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;
        $filters = [];
        if ($request->filled('game_id')) {
            $filters['game_id'] = (int) $request->input('game_id');
        }
        if ($request->filled('localization_id')) {
            $filters['localization_id'] = (int) $request->input('localization_id');
        }
        if ($request->filled('server_id')) {
            $filters['server_id'] = (int) $request->input('server_id');
        }
        return $this->guildRepository->getPaginatedWithContext($perPage, $filters);
    }
}
