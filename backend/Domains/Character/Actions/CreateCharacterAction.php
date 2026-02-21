<?php

namespace Domains\Character\Actions;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Models\User;
use Domains\Character\Models\Character;

class CreateCharacterAction
{
    public function __construct(
        private CharacterRepositoryInterface $characterRepository
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(User $user, array $data): Character
    {
        $data['user_id'] = $user->id;
        $character = $this->characterRepository->create($data);
        $character->load(['game', 'localization', 'server']);
        return $character;
    }
}
