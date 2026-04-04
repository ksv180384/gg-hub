<?php

namespace Domains\Poll\Actions;

use App\Actions\Notification\CreatePollDeletedByAdminNotificationAction;
use Domains\Poll\Models\Poll;

/**
 * Удаление голосования администратором с отправкой уведомления автору.
 */
class AdminDeletePollAction
{
    public function __construct(
        private CreatePollDeletedByAdminNotificationAction $createPollDeletedNotificationAction
    ) {}

    public function __invoke(Poll $poll, string $reason): void
    {
        ($this->createPollDeletedNotificationAction)($poll, $reason);

        $poll->delete();
    }
}
