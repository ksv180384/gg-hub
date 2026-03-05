<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryParticipant;
use Domains\Event\Models\EventHistoryScreenshot;
use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateEventHistoryAction
{
    /**
     * @param  array{
     *     guild_id: int,
     *     title: string,
     *     description?: string|null,
     *     occurred_at?: string|null,
     *     participants?: array<int, array{character_id?: int|null, external_name?: string|null}>,
     *     screenshots?: array<int, array{url: string, title?: string|null, sort_order?: int|null}>
     * }  $data
     */
    public function __invoke(array $data): EventHistory
    {
        return DB::transaction(function () use ($data): EventHistory {
            /** @var EventHistoryTitle $title */
            $title = EventHistoryTitle::query()->firstOrCreate([
                'name' => $data['title'],
            ]);

            $payload = [
                'guild_id' => $data['guild_id'],
                'event_history_title_id' => $title->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'occurred_at' => isset($data['occurred_at']) && $data['occurred_at'] !== null
                    ? Carbon::parse($data['occurred_at'])
                    : now(),
            ];

            /** @var EventHistory $history */
            $history = EventHistory::query()->create($payload);

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

            $screenshots = $data['screenshots'] ?? [];
            foreach ($screenshots as $index => $screenshot) {
                EventHistoryScreenshot::query()->create([
                    'event_history_id' => $history->id,
                    'url' => $screenshot['url'],
                    'title' => $screenshot['title'] ?? null,
                    'sort_order' => $screenshot['sort_order'] ?? $index,
                ]);
            }

            return $history->load([
                'participants.character:id,name',
                'screenshots',
            ]);
        });
    }
}

