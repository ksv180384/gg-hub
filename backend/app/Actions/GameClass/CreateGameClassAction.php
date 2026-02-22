<?php

namespace App\Actions\GameClass;

use App\Models\Game;
use App\Models\GameClass;
use App\Services\GameClassImageService;
use Illuminate\Http\UploadedFile;

class CreateGameClassAction
{
    public function __construct(
        private GameClassImageService $gameClassImageService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(Game $game, array $data, ?UploadedFile $image = null): GameClass
    {
        $slug = isset($data['slug']) && trim((string) $data['slug']) !== ''
            ? trim((string) $data['slug'])
            : $this->slugFromName(trim((string) ($data['name'] ?? '')));
        unset($data['slug'], $data['image']);
        $data['game_id'] = $game->id;
        $data['slug'] = $slug;
        $gameClass = GameClass::create($data);
        if ($image !== null) {
            $path = $this->gameClassImageService->storeWithVariants($image, $gameClass->id);
            $gameClass->update(['image' => $path]);
        }
        return $gameClass;
    }

    private function slugFromName(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/\s+/u', '-', $slug) ?? $slug;
        $slug = preg_replace('/[^a-z0-9\-]/u', '', $slug) ?? $slug;
        $slug = preg_replace('/-+/', '-', $slug) ?? $slug;
        return trim($slug, '-') ?: 'class';
    }
}
