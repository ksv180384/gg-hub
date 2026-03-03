<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryParticipant;
use Domains\Event\Models\EventHistoryScreenshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateEventHistoryAction
{
    /**
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     occurred_at?: string|null,
     *     participants?: array<int, array{character_id?: int|null, external_name?: string|null}>,
     *     screenshots?: array<int, array{url: string, title?: string|null, sort_order?: int|null}>
     * }  $data
     */
    public function __invoke(EventHistory $history, array $data): EventHistory
    {
        return DB::transaction(function () use ($history, $data): EventHistory {
            $update = [];

            if (array_key_exists('title', $data)) {
                $update['title'] = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $update['description'] = $data['description'];
            }
            if (array_key_exists('occurred_at', $data)) {
                $update['occurred_at'] = $data['occurred_at'] !== null
                    ? Carbon::parse($data['occurred_at'])
                    : null;
            }

            if ($update !== []) {
                $history->fill($update);
                $history->save();
            }

            if (array_key_exists('participants', $data)) {
                EventHistoryParticipant::query()
                    ->where('event_history_id', $history->id)
                    ->delete();

                $participants = $data['participants'] ?? [];
                foreach ($participants as $participant) {
                    if (
                        empty($participant['character_id'])
                        && (empty($participant['external_name']) || ! is_string($participant['external_name']))
                    ) {
                        continue;
                    }

                    EventHistoryParticipant::query()->create([
                        'event_history_id' => $history->id,
                        'character_id' => $participant['character_id'] ?? null,
                        'external_name' => $participant['external_name'] ?? null,
                    ]);
                }
            }

            if (array_key_exists('screenshots', $data)) {
                EventHistoryScreenshot::query()
                    ->where('event_history_id', $history->id)
                    ->delete();

                $screenshots = $data['screenshots'] ?? [];
                foreach ($screenshots as $index => $screenshot) {
                    EventHistoryScreenshot::query()->create([
                        'event_history_id' => $history->id,
                        'url' => $screenshots[$index]['url'],
                        'title' => $screenshots[$index]['title'] ?? null,
                        'sort_order' => $screenshots[$index]['sort_order'] ?? $index,
                    ]);
                }
            }

            return $history->load([
                'participants.character:id,name',
                'screenshots',
            ]);
        });
    }
}

