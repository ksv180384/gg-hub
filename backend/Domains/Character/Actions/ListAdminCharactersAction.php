<?php

namespace Domains\Character\Actions;

use Domains\Character\Models\Character;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListAdminCharactersAction
{
    private const PER_PAGE = 50;

    /**
     * @param  array{name?: string|null, email?: string|null, game_id?: int|null, server_id?: int|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, Character>
     */
    public function __invoke(array $filters): LengthAwarePaginator
    {
        $sortColumns = [
            'name' => 'characters.name',
            'email' => 'users.email',
            'game' => 'games.name',
            'server' => 'servers.name',
        ];

        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        return Character::query()
            ->select('characters.*')
            ->join('users', 'users.id', '=', 'characters.user_id')
            ->join('games', 'games.id', '=', 'characters.game_id')
            ->join('servers', 'servers.id', '=', 'characters.server_id')
            ->with(['user:id,email', 'game:id,name', 'server:id,name'])
            ->when($this->filterValue($filters, 'name'), function (Builder $query, string $value): void {
                $query->where('characters.name', 'like', "%{$value}%");
            })
            ->when($this->filterValue($filters, 'email'), function (Builder $query, string $value): void {
                $query->where('users.email', 'like', "%{$value}%");
            })
            ->when($filters['game_id'] ?? null, function (Builder $query, int $gameId): void {
                $query->where('characters.game_id', $gameId);
            })
            ->when($filters['server_id'] ?? null, function (Builder $query, int $serverId): void {
                $query->where('characters.server_id', $serverId);
            })
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('characters.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filterValue(array $filters, string $key): ?string
    {
        $value = trim((string) ($filters[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
