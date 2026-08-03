<?php

namespace Domains\Guild\Actions;

use App\Filters\GuildFilter;
use App\Repositories\Eloquent\EloquentGuildRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ListGuildsAction
{
    public function __construct(
        private EloquentGuildRepository $guildRepository
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
