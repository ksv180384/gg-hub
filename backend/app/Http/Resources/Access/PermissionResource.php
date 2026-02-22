<?php

namespace App\Http\Resources\Access;

use Domains\Access\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope?->value ?? $this->scope,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'permission_group_id' => $this->permission_group_id,
            'group' => $this->whenLoaded('group', fn () => $this->group ? [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'slug' => $this->group->slug,
            ] : null),
        ];
    }
}
