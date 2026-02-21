<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Models\User;
use Domains\Guild\Models\Guild;

class CreateGuildAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(User $user, array $data): Guild
    {
        $data['owner_id'] = $user->id;
        $guild = $this->guildRepository->create($data);
        $guild->load(['game', 'localization', 'server']);
        return $guild;
    }
}
