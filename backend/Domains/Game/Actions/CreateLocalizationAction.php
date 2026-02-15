<?php

namespace Domains\Game\Actions;

use App\Contracts\Repositories\LocalizationRepositoryInterface;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;

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
