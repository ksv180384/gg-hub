<?php

namespace App\Observers;

use App\Services\GuildPollSocketBroadcaster;
use Domains\Poll\Models\Poll;

/**
 * Автоматически транслирует изменения голосований в socket_server,
 * чтобы действиям не приходилось вызывать брадкастер руками для простых CRUD.
 *
 * Изменения голосов (VotePollAction/WithdrawPollVoteAction) не трогают модель Poll,
 * поэтому дополнительно брадкастятся вручную из этих действий.
 */
class PollObserver
{
    public function __construct(
        private readonly GuildPollSocketBroadcaster $broadcaster
    ) {}

    public function created(Poll $poll): void
    {
        $this->broadcaster->broadcastChanged($poll);
    }

    public function updated(Poll $poll): void
    {
        $this->broadcaster->broadcastChanged($poll);
    }

    public function deleted(Poll $poll): void
    {
        $this->broadcaster->broadcastDeleted((int) $poll->guild_id, (int) $poll->id);
    }
}
