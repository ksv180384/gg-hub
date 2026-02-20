<?php

namespace App\Http\Resources\Access;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс ответа PUT /users/{id}/roles-permissions: id, permissions, roles.
 *
 * @mixin User
 */
class UserRolesPermissionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'permissions' => $this->getAllPermissionSlugs(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ])->all()),
        ];
    }
}
