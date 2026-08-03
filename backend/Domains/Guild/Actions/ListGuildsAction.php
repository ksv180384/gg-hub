<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Filters\GuildFilter;
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

        return $this->guildRepository->getPaginatedWithContext(
            $perPage,
            new GuildFilter($request),
        );
    }
}
