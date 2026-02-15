<?php

namespace App\Actions\Game;

use App\Contracts\Repositories\LocalizationRepositoryInterface;
use App\Models\Game;
use App\Models\Localization;

class CreateLocalizationAction
{
    public function __construct(
        private LocalizationRepositoryInterface $localizationRepository
    ) {}

    public function execute(Game $game, array $data): Localization
    {
        return $this->localizationRepository->createForGame($game, $data);
    }
}
