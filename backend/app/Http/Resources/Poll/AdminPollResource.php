<?php

namespace App\Http\Resources\Poll;

use Domains\Poll\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Poll */
class AdminPollResource extends JsonResource
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

        unset($base['my_vote_option_id'], $base['my_vote_character_id']);

        return $base;
    }
}
