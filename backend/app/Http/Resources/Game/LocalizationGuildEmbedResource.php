<?php

namespace App\Http\Resources\Game;

use Domains\Game\Models\Localization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Локализация в контексте гильдии.
 *
 * @mixin Localization
 */
class LocalizationGuildEmbedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
