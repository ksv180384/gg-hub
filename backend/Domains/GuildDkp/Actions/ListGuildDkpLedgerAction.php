<?php

namespace Domains\GuildDkp\Actions;

use App\Filters\GuildDkpLedgerFilter;
use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Database\Eloquent\Collection;

class ListGuildDkpLedgerAction
{
    /** @return Collection<int, GuildDkpLedgerEntry> */
    public function __invoke(Guild $guild, ?GuildDkpLedgerFilter $filter = null): Collection
    {
        $query = GuildDkpLedgerEntry::query()
            ->where('guild_id', $guild->id)
            ->with([
                'user:id,name',
                'actorUser:id,name',
                'character:id,name',
                'guildBankItem:id,name',
                'eventHistory:id,event_history_title_id,occurred_at',
                'eventHistory.titleReference:id,name',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($filter) {
            $query->filter($filter);
        }

        return $query->get();
    }
}
