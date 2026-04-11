<?php

namespace App\Http\Resources\Tag;

use Domains\Tag\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Минимальные поля тега для отображения (карточка гильдии, страница информации).
 *
 * @mixin Tag
 */
class TagLabelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
