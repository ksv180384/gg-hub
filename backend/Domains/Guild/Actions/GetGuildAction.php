<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use Domains\Guild\Models\Guild;

class GetGuildAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository
    ) {}

    public function __invoke(int $id): ?Guild
    {
        $guild = $this->guildRepository->findById($id);
        if ($guild) {
            $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader']);
        }
        return $guild;
    }
}
