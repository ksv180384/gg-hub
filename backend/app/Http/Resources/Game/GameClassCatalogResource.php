<?php

namespace App\Http\Resources\Game;

use App\Models\GameClass;
use App\Services\GameClassImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Публичный список классов игры (GET /games/{game}/game-classes): только поля для фильтров и селектов.
 *
 * @mixin GameClass
 */
class GameClassCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');
        $imageThumbUrl = $this->image ? $disk->url(GameClassImageService::thumbPath($this->image)) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ru' => $this->name_ru,
            'image_thumb' => $imageThumbUrl,
        ];
    }
}
