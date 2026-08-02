<?php

declare(strict_types=1);

namespace App\Http\Resources\GuildActivity;

use App\GuildActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildActivityLog */
class GuildActivityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'category' => $this->category,
            'action' => $this->action,
            'description' => $this->description,
            'subject' => $this->subject_id ? [
                'type' => $this->subject_type,
                'id' => (int) $this->subject_id,
                'name' => $this->subject_name,
            ] : null,
            'actor' => $this->actor_user_id || $this->actor_name ? [
                'id' => $this->actor_user_id ? (int) $this->actor_user_id : null,
                'name' => $this->actor_name ?? $this->actor?->name,
            ] : null,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
