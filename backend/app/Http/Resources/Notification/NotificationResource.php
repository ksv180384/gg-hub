<?php

namespace App\Http\Resources\Notification;

use Domains\Notification\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'guild_id' => $this->guild_id,
            'game' => $this->whenLoaded('game', fn () => $this->game ? [
                'id' => $this->game->id,
                'name' => $this->game->name,
            ] : null),
            'guild' => $this->whenLoaded('guild', fn () => $this->guild ? [
                'id' => $this->guild->id,
                'name' => $this->guild->name,
            ] : null),
            'message' => $this->message,
            'link' => $this->link,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
