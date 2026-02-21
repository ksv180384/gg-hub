<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\Game\GameClassResource;
use App\Models\Game;
use App\Services\GameImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Game */
class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');
        $imageUrl = $this->image ? $disk->url($this->image) : null;
        $imagePreviewUrl = $this->image ? $disk->url(GameImageService::previewPath($this->image)) : null;
        $imageThumbUrl = $this->image ? $disk->url(GameImageService::thumbPath($this->image)) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $imageUrl,
            'image_preview' => $imagePreviewUrl,
            'image_thumb' => $imageThumbUrl,
            'is_active' => $this->is_active,
            'max_classes_per_character' => $this->max_classes_per_character ?? 1,
            'localizations' => LocalizationResource::collection($this->whenLoaded('localizations')),
            'game_classes' => GameClassResource::collection($this->whenLoaded('gameClasses')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
