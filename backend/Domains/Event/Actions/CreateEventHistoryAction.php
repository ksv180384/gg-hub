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

class CreateEventHistoryAction
{
    public function __construct(
        private SyncEventHistoryDkpLedgerAction $syncEventHistoryDkpLedgerAction,
        private ResolveEventParticipantDkpCoefficient $resolveEventParticipantDkpCoefficient,
    ) {}
    /**
     * @param  array{
     *     guild_id: int,
     *     title: string,
     *     description?: string|null,
     *     occurred_at?: string|null,
     *     dkp_base_points?: int|null,
     *     participants?: array<int, array{character_id?: int|null, external_name?: string|null, dkp_coefficient?: float|int|string|null, dkp_points_override?: int|null}>,
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
                'dkp_base_points' => array_key_exists('dkp_base_points', $data)
                    ? $data['dkp_base_points']
                    : $title->dkp_base_points,
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
                    'dkp_coefficient' => ($this->resolveEventParticipantDkpCoefficient)($data['guild_id'], $participant),
                    'dkp_points_override' => $participant['dkp_points_override'] ?? null,
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

