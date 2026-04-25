<?php

namespace Domains\Poll\Actions;

use App\Services\GuildPollSocketBroadcaster;
use Domains\Character\Models\Character;
use Domains\Poll\Models\Poll;
use Domains\Poll\Models\PollVote;
use Illuminate\Validation\ValidationException;

class WithdrawPollVoteAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction,
        private GuildPollSocketBroadcaster $broadcaster
    ) {}

    /**
     * Отзыв голоса. Удаляет голос пользователя (одного из его персонажей) в голосовании.
     */
    public function __invoke(Poll $poll, int $characterId): void
    {
        ($this->closeExpiredPollAction)($poll);

        if ($poll->is_closed) {
            throw ValidationException::withMessages(['poll' => ['Голосование закрыто.']]);
        }

        $character = Character::query()->find($characterId);
        if ($character === null) {
            throw ValidationException::withMessages(['character_id' => ['Персонаж не найден.']]);
        }

        $isMember = $poll->guild->members()
            ->where('character_id', $characterId)
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages(['character_id' => ['Персонаж не состоит в гильдии.']]);
        }

        $userCharacterIds = $poll->guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $character->user_id))
            ->pluck('character_id');

        $deleted = PollVote::query()
            ->where('poll_id', $poll->id)
            ->whereIn('character_id', $userCharacterIds)
            ->delete();

        if ($deleted > 0) {
            $this->broadcaster->broadcastChanged($poll);
        }
    }
}
