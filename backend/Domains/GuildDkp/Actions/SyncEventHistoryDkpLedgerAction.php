<?php

namespace Domains\GuildDkp\Actions;

use Domains\Event\Models\EventHistory;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Domains\GuildDkp\Models\GuildUserDkpBalance;
use Domains\GuildDkp\Support\CalculateEventParticipantDkpPoints;
use Illuminate\Support\Facades\DB;

class SyncEventHistoryDkpLedgerAction
{
    public function __construct(
        private RecordGuildDkpLedgerEntryAction $recordGuildDkpLedgerEntryAction,
        private ResolveGuildMemberUserIdAction $resolveGuildMemberUserIdAction,
    ) {}

    public function __invoke(EventHistory $history): void
    {
        $history->loadMissing(['guild', 'titleReference', 'participants.character:id,user_id']);

        $guild = $history->guild;
        if ($guild === null || ! (bool) ($guild->dkp_enabled ?? false)) {
            $this->clearEventLedger($history);

            return;
        }

        DB::transaction(function () use ($history, $guild): void {
            $this->clearEventLedger($history);

            $basePoints = $history->dkp_base_points === null ? null : (int) $history->dkp_base_points;
            if ($basePoints === null) {
                return;
            }

            $distributeTotal = (bool) ($history->distribute_dkp_to_participants
                ?? $history->titleReference?->distribute_dkp_to_participants
                ?? false);

            $participantPayloads = $history->participants
                ->values()
                ->map(fn ($participant) => [
                    'character_id' => $participant->character_id,
                    'dkp_coefficient' => $participant->dkp_coefficient,
                    'dkp_points_override' => $participant->dkp_points_override === null
                        ? null
                        : (int) $participant->dkp_points_override,
                ])
                ->all();

            $amounts = CalculateEventParticipantDkpPoints::resolveAll(
                $basePoints,
                $distributeTotal,
                $participantPayloads,
            );

            foreach ($history->participants->values() as $index => $participant) {
                if ($participant->character_id === null) {
                    continue;
                }

                $amount = $amounts[$index] ?? null;

                if ($amount === null || $amount === 0) {
                    continue;
                }

                $userId = ($this->resolveGuildMemberUserIdAction)($guild, (int) $participant->character_id);

                ($this->recordGuildDkpLedgerEntryAction)($guild, [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'occurred_at' => $history->occurred_at,
                    'source' => GuildDkpLedgerSource::Event,
                    'event_history_id' => $history->id,
                    'event_history_participant_id' => $participant->id,
                    'character_id' => (int) $participant->character_id,
                    'reason' => $history->title,
                ]);
            }
        });
    }

    public function clear(EventHistory $history): void
    {
        DB::transaction(fn () => $this->clearEventLedger($history));
    }

    private function clearEventLedger(EventHistory $history): void
    {
        $entries = GuildDkpLedgerEntry::query()
            ->where('event_history_id', $history->id)
            ->where('source', GuildDkpLedgerSource::Event)
            ->lockForUpdate()
            ->get();

        foreach ($entries as $entry) {
            $this->reverseEntry($entry);
            $entry->delete();
        }
    }

    private function reverseEntry(GuildDkpLedgerEntry $entry): void
    {
        $balance = GuildUserDkpBalance::query()
            ->where('guild_id', $entry->guild_id)
            ->where('user_id', $entry->user_id)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            return;
        }

        $balance->balance = (int) $balance->balance - (int) $entry->amount;
        $balance->save();
    }
}
