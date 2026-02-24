<?php

namespace App\Http\Resources\Guild;

use Domains\Guild\Models\GuildApplicationFormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildApplicationFormField */
class GuildApplicationFormFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'sort_order' => $this->sort_order,
        ];
    }
}
