<?php

namespace App\Http\Resources\Poll;

use Domains\Guild\Models\GuildMember;
use Domains\Poll\Models\Poll;
use Domains\Poll\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Poll */
class PollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = $this->options->map(function ($opt) {
            $item = [
                'id' => $opt->id,
                'text' => $opt->text,
                'sort_order' => $opt->sort_order,
                'votes_count' => $opt->votes_count ?? $opt->votes()->count(),
            ];
            if (!$this->is_anonymous && $opt->relationLoaded('votes')) {
                $item['voters'] = $opt->votes
                    ->filter(fn ($v) => $v->relationLoaded('character') && $v->character)
                    ->map(fn ($v) => ['character_id' => $v->character_id, 'name' => $v->character->name])
                    ->values()
                    ->all();
            }
            return $item;
        })->values()->all();

        $totalVotes = collect($options)->sum('votes_count');

        $user = $request->user();
        $myVoteOptionId = null;
        $myVoteCharacterId = null;
        if ($user) {
            $characterIds = GuildMember::query()
                ->where('guild_id', $this->guild_id)
                ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('character_id');
            $vote = PollVote::query()
                ->where('poll_id', $this->id)
                ->whereIn('character_id', $characterIds)
                ->first();
            $myVoteOptionId = $vote?->option_id;
            $myVoteCharacterId = $vote?->character_id;
        }

        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'title' => $this->title,
            'description' => $this->description,
            'is_anonymous' => $this->is_anonymous,
            'is_closed' => $this->is_closed,
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_by_character_id' => $this->created_by_character_id,
            'creator_character' => $this->whenLoaded('creatorCharacter', fn () => $this->creatorCharacter ? [
                'id' => $this->creatorCharacter->id,
                'name' => $this->creatorCharacter->name,
            ] : null),
            'options' => $options,
            'total_votes' => $totalVotes,
            'my_vote_option_id' => $myVoteOptionId,
            'my_vote_character_id' => $myVoteCharacterId,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
