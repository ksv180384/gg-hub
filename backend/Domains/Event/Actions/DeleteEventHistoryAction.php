<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\GuildDkp\Actions\SyncEventHistoryDkpLedgerAction;

class DeleteEventHistoryAction
{
    public function __construct(
        private SyncEventHistoryDkpLedgerAction $syncEventHistoryDkpLedgerAction,
    ) {}

    public function __invoke(EventHistory $history): void
    {
        ($this->syncEventHistoryDkpLedgerAction)->clear($history);
        $history->delete();
    }
}

