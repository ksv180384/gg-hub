<?php

namespace App\Actions\Character;

use App\Actions\Notification\CreateFirstCharacterGuildSuggestionNotificationAction;
use Domains\Character\Models\Character;

class HandleFirstCharacterCreatedAction
{
    public function __construct(
        private CreateFirstCharacterGuildSuggestionNotificationAction $createFirstCharacterGuildSuggestionNotificationAction,
    ) {}

    public function __invoke(Character $character): void
    {
        $userId = (int) $character->user_id;
        if ($userId <= 0) {
            return;
        }

        $count = Character::query()
            ->where('user_id', $userId)
            ->count();

        if ($count !== 1) {
            return;
        }

        ($this->createFirstCharacterGuildSuggestionNotificationAction)($character);
    }
}

