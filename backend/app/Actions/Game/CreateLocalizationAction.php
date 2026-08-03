<?php

namespace App\Actions\Game;

use App\Repositories\Eloquent\EloquentLocalizationRepository;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;

class CreateLocalizationAction
{
    public function __construct(
        private EloquentLocalizationRepository $localizationRepository
    ) {}

    public function __invoke(Game $game, array $data): Localization
    {
        return $this->localizationRepository->createForGame($game, $data);
    }
}
