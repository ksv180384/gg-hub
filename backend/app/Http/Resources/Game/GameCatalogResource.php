<?php

namespace App\Http\Resources\Game;

use App\Models\Game;
use App\Services\GameImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Game */
class GameCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');
        $imagePreviewUrl = $this->image ? $disk->url(GameImageService::previewPath($this->image)) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_preview' => $imagePreviewUrl,
            'is_active' => (bool) $this->is_active,
        ];
    }
}

