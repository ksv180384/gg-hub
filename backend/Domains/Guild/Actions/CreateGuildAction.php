<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use Domains\Guild\Models\Guild;

class CreateGuildAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository
    ) {}

    public function execute(array $data): Guild
    {
        return $this->guildRepository->create($data);
    }
}
