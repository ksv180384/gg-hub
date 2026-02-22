<?php

namespace App\Actions\GameClass;

use App\Models\GameClass;
use App\Services\GameClassImageService;
use Illuminate\Http\UploadedFile;

class UpdateGameClassAction
{
    public function __construct(
        private GameClassImageService $gameClassImageService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(GameClass $gameClass, array $data, ?UploadedFile $image = null, bool $removeImage = false): GameClass
    {
        if (isset($data['slug']) && trim((string) $data['slug']) === '') {
            $data['slug'] = $this->slugFromName(trim((string) ($data['name'] ?? $gameClass->name)));
        }
        if (array_key_exists('name_ru', $data) && trim((string) $data['name_ru']) === '') {
            $data['name_ru'] = null;
        }
        unset($data['image'], $data['remove_image']);
        $gameClass->update($data);
        if ($removeImage) {
            $gameClass->deleteImageFiles();
            $gameClass->update(['image' => null]);
        } elseif ($image !== null) {
            $gameClass->deleteImageFiles();
            $path = $this->gameClassImageService->storeWithVariants($image, $gameClass->id);
            $gameClass->update(['image' => $path]);
        }
        return $gameClass->fresh();
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
