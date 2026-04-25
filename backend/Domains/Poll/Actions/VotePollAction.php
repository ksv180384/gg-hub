<?php

namespace Domains\Poll\Actions;

use App\Services\GuildPollSocketBroadcaster;
use Domains\Character\Models\Character;
use Domains\Poll\Models\Poll;
use Domains\Poll\Models\PollVote;
use Illuminate\Validation\ValidationException;

class VotePollAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction,
        private GuildPollSocketBroadcaster $broadcaster
    ) {}

    /**
     * Голосование за вариант. Один голос на пользователя (независимо от количества персонажей).
     * Можно менять голос до закрытия голосования.
     */
    public function __invoke(Poll $poll, int $characterId, int $optionId): void
    {
        ($this->closeExpiredPollAction)($poll);

        if ($poll->is_closed) {
            throw ValidationException::withMessages(['poll' => ['Голосование закрыто.']]);
        }

        $option = $poll->options()->find($optionId);
        if ($option === null) {
            throw ValidationException::withMessages(['option_id' => ['Вариант не найден.']]);
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

        PollVote::query()
            ->where('poll_id', $poll->id)
            ->whereIn('character_id', $userCharacterIds)
            ->delete();

        PollVote::create([
            'poll_id' => $poll->id,
            'option_id' => $optionId,
            'character_id' => $characterId,
        ]);

        $this->broadcaster->broadcastChanged($poll);
    }
}
