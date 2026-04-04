<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;
use Domains\Poll\Models\PollOption;
use Illuminate\Support\Facades\DB;

class CreatePollAction
{
    /**
     * @param  array{title: string, description?: string|null, options: array<int, string>}  $data
     */
    public function __invoke(array $data): Poll
    {
        return DB::transaction(function () use ($data) {
            $poll = Poll::create([
                'guild_id' => $data['guild_id'],
                'created_by' => $data['created_by'] ?? null,
                'created_by_character_id' => $data['created_by_character_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_anonymous' => $data['is_anonymous'] ?? true,
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            $options = $data['options'] ?? [];
            foreach ($options as $index => $text) {
                if (is_string($text) && trim($text) !== '') {
                    PollOption::create([
                        'poll_id' => $poll->id,
                        'text' => trim($text),
                        'sort_order' => $index,
                    ]);
                }
            }

            return $poll->load(['options', 'creatorCharacter:id,name']);
        });
    }
}
