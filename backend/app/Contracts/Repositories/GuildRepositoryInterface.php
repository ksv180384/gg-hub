<?php

namespace App\Contracts\Repositories;

use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GuildRepositoryInterface
{
    /**
     * Гильдии с игрой, локализацией и сервером (с пагинацией).
     *
     * @param array{game_id?: int, localization_id?: int, server_id?: int} $filters
     * @return LengthAwarePaginator<Guild>
     */
    public function getPaginatedWithContext(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Guild;

    public function findById(int $id): ?Guild;

    /**
     * @param array<string, mixed> $data
     */
    public function update(Guild $guild, array $data): Guild;
}
