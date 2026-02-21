<?php

namespace App\Http\Resources\Game;

use App\Models\GameClass;
use App\Services\GameClassImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin GameClass */
class GameClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');
        $imageUrl = $this->image ? $disk->url($this->image) : null;
        $imagePreviewUrl = $this->image ? $disk->url(GameClassImageService::previewPath($this->image)) : null;
        $imageThumbUrl = $this->image ? $disk->url(GameClassImageService::thumbPath($this->image)) : null;

        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'name' => $this->name,
            'name_ru' => $this->name_ru,
            'slug' => $this->slug,
            'image' => $imageUrl,
            'image_preview' => $imagePreviewUrl,
            'image_thumb' => $imageThumbUrl,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
