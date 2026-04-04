<?php

namespace App\Http\Resources\Guild;

use Domains\Guild\Models\GuildApplicationComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuildApplicationComment */
class AdminGuildApplicationCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->guild_application_id,
            'body' => $this->body,
            'is_hidden' => (bool) $this->is_hidden,
            'hidden_reason' => $this->hidden_reason,
            'delete_reason' => $this->delete_reason,
            'is_deleted' => $this->trashed(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'author_name' => $this->character?->name ?? $this->user?->name ?? 'Пользователь',
            'author_avatar_url' => $this->character?->resolved_avatar_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'guild_id' => $this->application?->guild_id,
            'guild_name' => $this->application?->guild?->name,
            'application_status' => $this->application?->status,
        ];
    }
}
