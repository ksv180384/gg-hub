<?php

namespace Domains\GuildDkp\Actions;

use App\Filters\GuildDkpLedgerFilter;
use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ListGuildDkpLedgerAction
{
    /**
     * @param  array{page?: int, per_page?: int}  $params
     * @return Collection<int, GuildDkpLedgerEntry>|LengthAwarePaginator
     */
    public function __invoke(Guild $guild, ?GuildDkpLedgerFilter $filter = null, array $params = []): Collection|LengthAwarePaginator
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

        $perPage = $params['per_page'] ?? 50;
        $page = max(1, (int) ($params['page'] ?? 1));

        if ($perPage > 0) {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        return $query->get();
    }
}
