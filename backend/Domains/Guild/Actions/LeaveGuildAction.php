<?php

namespace Domains\Guild\Actions;

use App\Actions\Notification\CreateGuildMemberLeftNotificationAction;
use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

class LeaveGuildAction
{
    public function __construct(
        private CreateGuildMemberLeftNotificationAction $createGuildMemberLeftNotificationAction
    ) {}

    public function __invoke(User $user, Guild $guild): void
    {
        $guild->loadMissing(['leader', 'members.character']);

        // Находим участника по пользователю (по его персонажу)
        $member = $guild->members
            ->first(fn (GuildMember $m) => $m->character && (int) $m->character->user_id === (int) $user->id);

        if (!$member) {
            throw ValidationException::withMessages([
                'guild' => ['Вы не состоите в этой гильдии.'],
            ]);
        }

        // Лидер не может покинуть гильдию таким образом
        if ($guild->leader_character_id && $member->character_id === (int) $guild->leader_character_id) {
            throw ValidationException::withMessages([
                'guild' => ['Лидер не может покинуть гильдию. Передайте лидерство другому участнику.'],
            ]);
        }

        $leftCharacterName = $member->character?->name ?? 'Участник';

        $member->delete();

        ($this->createGuildMemberLeftNotificationAction)($guild, $leftCharacterName);
    }
}

