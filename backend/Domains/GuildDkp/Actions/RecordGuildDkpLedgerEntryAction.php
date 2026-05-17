<?php

namespace Domains\GuildDkp\Actions;

use Carbon\CarbonInterface;
use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Domains\GuildDkp\Models\GuildUserDkpBalance;
use Illuminate\Support\Carbon;

class RecordGuildDkpLedgerEntryAction
{
    /**
     * @param  array{
     *     user_id: int,
     *     amount: int,
     *     occurred_at?: CarbonInterface|string|null,
     *     source: GuildDkpLedgerSource,
     *     event_history_id?: int|null,
     *     event_history_participant_id?: int|null,
     *     guild_bank_item_grant_id?: int|null,
     *     guild_bank_item_id?: int|null,
     *     character_id?: int|null,
     *     actor_user_id?: int|null,
     *     reason?: string|null,
     * }  $data
     */
    public function __invoke(Guild $guild, array $data): GuildDkpLedgerEntry
    {
        $balance = GuildUserDkpBalance::query()
            ->where('guild_id', $guild->id)
            ->where('user_id', $data['user_id'])
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            $balance = GuildUserDkpBalance::query()->create([
                'guild_id' => $guild->id,
                'user_id' => $data['user_id'],
                'balance' => 0,
            ]);
            $balance = GuildUserDkpBalance::query()
                ->whereKey($balance->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $nextBalance = (int) $balance->balance + (int) $data['amount'];
        $balance->balance = $nextBalance;
        $balance->save();

        $occurredAt = $data['occurred_at'] ?? null;
        if ($occurredAt !== null && ! $occurredAt instanceof CarbonInterface) {
            $occurredAt = Carbon::parse($occurredAt);
        }

        return GuildDkpLedgerEntry::query()->create([
            'guild_id' => $guild->id,
            'user_id' => $data['user_id'],
            'amount' => (int) $data['amount'],
            'occurred_at' => $occurredAt ?? now(),
            'source' => $data['source'],
            'event_history_id' => $data['event_history_id'] ?? null,
            'event_history_participant_id' => $data['event_history_participant_id'] ?? null,
            'guild_bank_item_grant_id' => $data['guild_bank_item_grant_id'] ?? null,
            'guild_bank_item_id' => $data['guild_bank_item_id'] ?? null,
            'character_id' => $data['character_id'] ?? null,
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'reason' => isset($data['reason']) ? trim((string) $data['reason']) : null,
            'balance_after' => $nextBalance,
        ]);
    }
}
