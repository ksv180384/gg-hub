<?php

namespace Domains\Event\Actions;

use App\Services\EventHistoryScreenshotService;
use Domains\Event\Models\EventHistory;
use Domains\GuildDkp\Actions\SyncEventHistoryDkpLedgerAction;

class DeleteEventHistoryAction
{
    public function __construct(
        private SyncEventHistoryDkpLedgerAction $syncEventHistoryDkpLedgerAction,
        private EventHistoryScreenshotService $eventHistoryScreenshotService,
    ) {}

    public function __invoke(EventHistory $history): void
    {
        $history->loadMissing('screenshots');

        foreach ($history->screenshots as $screenshot) {
            $this->eventHistoryScreenshotService->deleteByUrl($screenshot->url);
        }

        ($this->syncEventHistoryDkpLedgerAction)->clear($history);
        $history->delete();
    }
}
