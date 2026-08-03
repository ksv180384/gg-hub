<?php

namespace App\Actions\Game;

use App\Repositories\Eloquent\EloquentGameRepository;
use App\Services\GameImageService;
use Domains\Game\Models\Game;
use Illuminate\Http\UploadedFile;

class CreateGameAction
{
    public function __construct(
        private EloquentGameRepository $gameRepository,
        private GameImageService $gameImageService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?UploadedFile $image = null): Game
    {
        unset($data['image']);
        $game = $this->gameRepository->create($data);
        if ($image !== null) {
            $path = $this->gameImageService->storeWithVariants($image, $game->id);
            $game->update(['image' => $path]);
        }
        $game->load('localizations');

        return $game;
    }
}
