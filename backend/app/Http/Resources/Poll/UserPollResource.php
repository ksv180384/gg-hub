<?php

namespace App\Http\Resources\Poll;

use Domains\Poll\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Poll */
class UserPollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new PollResource($this->resource))->toArray($request);

        $base['guild'] = $this->whenLoaded('guild', fn () => $this->guild ? [
            'id' => $this->guild->id,
            'name' => $this->guild->name,
        ] : null);

        $user = $request->user();
        $base['my_characters'] = [];
        if ($user && $this->relationLoaded('guild') && $this->guild) {
            $characters = $this->guild->members()
                ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
                ->with('character:id,name')
                ->orderBy('joined_at')
                ->get()
                ->map(fn ($m) => $m->character)
                ->filter()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->values()
                ->all();
            $base['my_characters'] = $characters;
        }

        return $base;
    }
}
