<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\UserAvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatarUrl = null;
        if ($this->avatar) {
            $avatarUrl = str_starts_with($this->avatar, 'users/')
                ? Storage::disk('public')->url(UserAvatarService::smallPath($this->avatar))
                : Storage::disk('public')->url($this->avatar);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $avatarUrl,
            'timezone' => $this->timezone ?? 'UTC',
            'banned_at' => $this->banned_at?->toIso8601String(),
            'permissions' => $this->getAllPermissionSlugs(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ])->all()),
        ];
    }
}
