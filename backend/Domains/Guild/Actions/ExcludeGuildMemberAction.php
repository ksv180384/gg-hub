<?php

namespace Domains\Guild\Actions;

use App\Actions\Notification\CreateGuildMemberExcludedNotificationAction;
use App\Actions\Notification\SendGuildDiscordNotificationAction;
use App\Models\User;
use App\Services\Notifications\GuildLinkBuilder;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

/**
 * Исключает участника гильдии по character_id.
 * Требуется право iskliucenie-polzovatelia-iz-gildii.
 * Лидера гильдии исключить нельзя.
 * Всем участникам гильдии и исключённому пользователю отправляются оповещения.
 */
final class ExcludeGuildMemberAction
{
    public function __construct(
        private CreateGuildMemberExcludedNotificationAction $createGuildMemberExcludedNotificationAction,
        private SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function __invoke(User $excludedBy, Guild $guild, int $characterId): void
    {
        $member = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->with('character')
            ->first();

        if (! $member || ! $member->character) {
            throw ValidationException::withMessages([
                'character_id' => ['Участник не найден в гильдии.'],
            ]);
        }

        if ($guild->leader_character_id && (int) $guild->leader_character_id === (int) $characterId) {
            throw ValidationException::withMessages([
                'character_id' => ['Нельзя исключить лидера гильдии. Передайте лидерство другому участнику.'],
            ]);
        }

        $excludedCharacterName = $member->character->name;
        $excludedUserId = (int) $member->character->user_id;

        $excluderCharacterName = 'Участник гильдии';
        $excluderMember = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $excludedBy->id))
            ->with('character')
            ->first();
        if ($excluderMember?->character) {
            $excluderCharacterName = $excluderMember->character->name;
        }

        // Участники кроме исключающего; исключённый пользователь добавляется явно — иначе при совпадении
        // user_id исключающего и исключённого (разные персонажи одного аккаунта) личное оповещение не создаётся.
        $recipientUserIds = $guild->members()
            ->with('character')
            ->get()
            ->pluck('character.user_id')
            ->filter()
            ->unique()
            ->values()
            ->reject(fn ($userId) => (int) $userId === (int) $excludedBy->id)
            ->values();

        if ($excludedUserId > 0) {
            $recipientUserIds->push($excludedUserId);
        }

        $recipientUserIds = $recipientUserIds->unique()->values()->all();

        $member->delete();

        ($this->createGuildMemberExcludedNotificationAction)(
            $guild,
            $excludedCharacterName,
            $excludedUserId,
            $recipientUserIds,
            $excluderCharacterName
        );

        $rosterUrl = $this->linkBuilder->rosterUrl($guild);
        $message = "Гильдию покинул {$excludedCharacterName} (исключён участником {$excluderCharacterName})\n{$rosterUrl}";
        ($this->sendGuildDiscordNotificationAction)(
            $guild,
            'discord_notify_member_left',
            $message,
        );
    }
}
