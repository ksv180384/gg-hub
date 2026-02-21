<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Services\SubdomainContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ListGuildsAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository,
        private SubdomainContext $subdomainContext
    ) {}

    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = (int) $request->input('per_page', 15);
        $filters = array_filter([
            'game_id' => $request->input('game_id') ? (int) $request->input('game_id') : null,
            'localization_id' => $request->input('localization_id') ? (int) $request->input('localization_id') : null,
            'server_id' => $request->input('server_id') ? (int) $request->input('server_id') : null,
        ]);

        $gameFromSubdomain = $this->subdomainContext->getGameBySubdomain($request);
        if ($gameFromSubdomain !== null) {
            $filters['game_id'] = $gameFromSubdomain->id;
        }

        return $this->guildRepository->getPaginatedWithContext($perPage, $filters);
    }
}
