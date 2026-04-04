<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;
use Domains\Poll\Models\PollOption;
use Illuminate\Support\Facades\DB;

class UpdatePollAction
{
    /**
     * @param  array{title?: string, description?: string|null, options?: array<int, string>}  $data
     */
    public function __invoke(Poll $poll, array $data): Poll
    {
        return DB::transaction(function () use ($poll, $data) {
            if (isset($data['title'])) {
                $poll->title = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $poll->description = $data['description'];
            }
            if (array_key_exists('is_anonymous', $data)) {
                $poll->is_anonymous = $data['is_anonymous'];
            }
            if (array_key_exists('ends_at', $data)) {
                $poll->ends_at = $data['ends_at'];
            }
            $poll->save();

            if (isset($data['options']) && is_array($data['options'])) {
                $poll->votes()->delete();
                $poll->options()->delete();

                foreach ($data['options'] as $index => $text) {
                    if (is_string($text) && trim($text) !== '') {
                        PollOption::create([
                            'poll_id' => $poll->id,
                            'text' => trim($text),
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            return $poll->fresh(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name']);
        });
    }
}
