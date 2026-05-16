<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryParticipant;
use Domains\Event\Models\EventHistoryScreenshot;
use Domains\Event\Models\EventHistoryTitle;
use Domains\Guild\Support\ResolveEventParticipantDkpCoefficient;
use Domains\GuildDkp\Actions\SyncEventHistoryDkpLedgerAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateEventHistoryAction
{
    public function __construct(
        private SyncEventHistoryDkpLedgerAction $syncEventHistoryDkpLedgerAction,
        private ResolveEventParticipantDkpCoefficient $resolveEventParticipantDkpCoefficient,
    ) {}
    /**
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     occurred_at?: string|null,
     *     dkp_base_points?: int|null,
     *     participants?: array<int, array{character_id?: int|null, external_name?: string|null, dkp_coefficient?: float|int|string|null, dkp_points_override?: int|null}>,
     *     screenshots?: array<int, array{url: string, title?: string|null, sort_order?: int|null}>
     * }  $data
     */
    public function __invoke(EventHistory $history, array $data): EventHistory
    {
        return DB::transaction(function () use ($history, $data): EventHistory {
            $update = [];

            if (array_key_exists('title', $data)) {
                $update['title'] = $data['title'];

                /** @var EventHistoryTitle $title */
                $title = EventHistoryTitle::query()->firstOrCreate([
                    'name' => $data['title'],
                ]);
                $update['event_history_title_id'] = $title->id;
            }
            if (array_key_exists('description', $data)) {
                $update['description'] = $data['description'];
            }
            if (array_key_exists('occurred_at', $data)) {
                $update['occurred_at'] = $data['occurred_at'] !== null
                    ? Carbon::parse($data['occurred_at'])
                    : null;
            }
            if (array_key_exists('dkp_base_points', $data)) {
                $update['dkp_base_points'] = $data['dkp_base_points'];
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
                        'dkp_coefficient' => ($this->resolveEventParticipantDkpCoefficient)((int) $history->guild_id, $participant),
                        'dkp_points_override' => $participant['dkp_points_override'] ?? null,
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

            $history = $history->load([
                'guild:id,dkp_enabled',
                'participants.character:id,name,user_id',
                'screenshots',
            ]);

            ($this->syncEventHistoryDkpLedgerAction)($history);

            return $history;
        });
    }
}

