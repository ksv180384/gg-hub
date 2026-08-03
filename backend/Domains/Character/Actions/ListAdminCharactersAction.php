<?php

namespace Domains\Character\Actions;

use App\Filters\AdminCharacterFilter;
use Domains\Character\Models\Character;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminCharactersAction
{
    private const PER_PAGE = 50;

    /**
     * @param  array{sort?: string|null, direction?: string|null}  $params
     * @return LengthAwarePaginator<int, Character>
     */
    public function __invoke(AdminCharacterFilter $filter, array $params): LengthAwarePaginator
    {
        $sortColumns = [
            'name' => 'characters.name',
            'email' => 'users.email',
            'game' => 'games.name',
            'server' => 'servers.name',
        ];

        $sort = $params['sort'] ?? 'name';
        $direction = $params['direction'] ?? 'asc';

        return Character::query()
            ->select('characters.*')
            ->join('users', 'users.id', '=', 'characters.user_id')
            ->join('games', 'games.id', '=', 'characters.game_id')
            ->join('servers', 'servers.id', '=', 'characters.server_id')
            ->with(['user:id,email', 'game:id,name', 'server:id,name'])
            ->filter($filter)
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('characters.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
