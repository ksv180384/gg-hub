<?php

namespace App\Actions\Game;

use App\Models\Game;
use App\Services\GameImageService;
use Illuminate\Http\UploadedFile;

class UpdateGameAction
{
    public function __construct(
        private GameImageService $gameImageService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(Game $game, array $data, ?UploadedFile $image = null, bool $removeImage = false): Game
    {
        unset($data['image'], $data['remove_image']);
        if (array_key_exists('max_classes_per_character', $data)) {
            $data['max_classes_per_character'] = (int) $data['max_classes_per_character'];
            if ($data['max_classes_per_character'] < 0) {
                $data['max_classes_per_character'] = 0;
            }
        }
        $game->update($data);

        if ($removeImage) {
            $game->deleteImageFiles();
            $game->update(['image' => null]);
        } elseif ($image !== null) {
            $game->deleteImageFiles();
            $path = $this->gameImageService->storeWithVariants($image, $game->id);
            $game->update(['image' => $path]);
        }

        $game->load('localizations');
        return $game;
    }
}
