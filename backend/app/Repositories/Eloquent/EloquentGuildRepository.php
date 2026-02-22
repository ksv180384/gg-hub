<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\GuildRepositoryInterface;
use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentGuildRepository implements GuildRepositoryInterface
{
    /**
     * @param array{game_id?: int, localization_id?: int, server_id?: int} $filters
     */
    public function getPaginatedWithContext(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Guild::query()
            ->with(['game', 'localization', 'server', 'leader'])
            ->withCount('members');

        if (isset($filters['game_id'])) {
            $query->where('game_id', $filters['game_id']);
        }
        if (isset($filters['localization_id'])) {
            $query->where('localization_id', $filters['localization_id']);
        }
        if (isset($filters['server_id'])) {
            $query->where('server_id', $filters['server_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Guild
    {
        return Guild::create($data);
    }

    public function findById(int $id): ?Guild
    {
        return Guild::query()->find($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Guild $guild, array $data): Guild
    {
        $guild->update($data);
        return $guild->fresh();
    }
}
