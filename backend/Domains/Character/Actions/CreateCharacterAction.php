<?php

namespace Domains\Character\Actions;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use Domains\Character\Models\Character;

class CreateCharacterAction
{
    public function __construct(
        private CharacterRepositoryInterface $characterRepository
    ) {}

    public function execute(array $data): Character
    {
        return $this->characterRepository->create($data);
    }
}
