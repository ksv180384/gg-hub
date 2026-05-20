<?php

namespace Domains\Guild\Actions;

use App\Actions\Notification\CreateGuildMemberLeftNotificationAction;
use App\Actions\Notification\SendGuildDiscordNotificationAction;
use App\Models\User;
use App\Services\Notifications\GuildLinkBuilder;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

class LeaveGuildAction
{
    public function __construct(
        private CreateGuildMemberLeftNotificationAction $createGuildMemberLeftNotificationAction,
        private SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function __invoke(User $user, Guild $guild, ?int $characterId = null): void
    {
        $guild->loadMissing(['leader', 'members.character']);

        $userMembers = $guild->members
            ->filter(fn (GuildMember $m) => $m->character && (int) $m->character->user_id === (int) $user->id);

        if ($characterId) {
            $member = $userMembers->first(
                fn (GuildMember $m) => (int) $m->character_id === $characterId
            );
        } else {
            $member = $userMembers->first(
                fn (GuildMember $m) => ! $guild->leader_character_id
                    || (int) $m->character_id !== (int) $guild->leader_character_id
            ) ?? $userMembers->first();
        }

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

        $rosterUrl = $this->linkBuilder->rosterUrl($guild);
        $message = "Гильдию покинул {$leftCharacterName}\n{$rosterUrl}";
        ($this->sendGuildDiscordNotificationAction)(
            $guild,
            'discord_notify_member_left',
            $message,
        );
    }
}
