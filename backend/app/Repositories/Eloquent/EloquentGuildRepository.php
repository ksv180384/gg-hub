<?php

namespace App\Repositories\Eloquent;

use App\Filters\GuildFilter;
use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentGuildRepository
{
    /**
     * @param  array{game_id?: int, localization_id?: int, server_id?: int}  $filters
     */
    public function getPaginatedWithContext(int $perPage, GuildFilter $filter): LengthAwarePaginator
    {
        $query = Guild::query()
            ->with(['game', 'localization', 'server', 'leader'])
            ->withCount('members');

        return $query
            ->filter($filter)
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
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
     * @param  array<string, mixed>  $data
     */
    public function update(Guild $guild, array $data): Guild
    {
        $guild->update($data);

        return $guild->fresh();
    }
}
