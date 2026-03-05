<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Validation\ValidationException;

class DeleteEventHistoryTitleAction
{
    public function __invoke(EventHistoryTitle $title): void
    {
        $isUsed = EventHistory::query()
            ->where('event_history_title_id', $title->id)
            ->exists();

        if ($isUsed) {
            throw ValidationException::withMessages([
                'title' => ['Нельзя удалить название, которое уже используется в событиях.'],
            ]);
        }

        $title->delete();
    }
}

