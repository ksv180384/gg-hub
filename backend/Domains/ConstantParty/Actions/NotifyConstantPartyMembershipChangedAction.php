<?php

namespace Domains\ConstantParty\Actions;

use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\ConstantParty\Models\ConstantPartyStorageLog;
use Domains\Notification\Models\Notification;

class NotifyConstantPartyMembershipChangedAction
{
    /** @return list<int> */
    public function recipientUserIds(ConstantParty $party): array
    {
        return ConstantPartyMember::query()
            ->join('characters', 'characters.id', '=', 'constant_party_members.character_id')
            ->where('constant_party_members.constant_party_id', $party->id)
            ->distinct()
            ->pluck('characters.user_id')
            ->map(static fn ($userId): int => (int) $userId)
            ->values()
            ->all();
    }

    /** @param list<int>|null $recipientUserIds */
    public function __invoke(
        ConstantParty $party,
        string $action,
        Character $subject,
        Character $actor,
        ?array $recipientUserIds = null,
        ?string $source = null,
    ): void {
        $recipientUserIds ??= $this->recipientUserIds($party);
        $message = $this->message($party, $action, $subject, $actor, $source);
        $isDeparture = in_array($action, [
            ConstantPartyStorageLog::ACTION_MEMBER_LEFT,
            ConstantPartyStorageLog::ACTION_MEMBER_REMOVED,
        ], true);

        foreach (array_values(array_unique($recipientUserIds)) as $userId) {
            Notification::query()->create([
                'user_id' => $userId,
                'message' => $message,
                'link' => $isDeparture && (int) $userId === (int) $subject->user_id
                    ? '/my-constant-parties'
                    : "/constant-parties/{$party->id}",
            ]);
        }
    }

    private function message(
        ConstantParty $party,
        string $action,
        Character $subject,
        Character $actor,
        ?string $source,
    ): string {
        $description = match ($action) {
            ConstantPartyStorageLog::ACTION_MEMBER_JOINED => "вступил в КП «{$party->name}»",
            ConstantPartyStorageLog::ACTION_MEMBER_LEFT => "покинул КП «{$party->name}»",
            ConstantPartyStorageLog::ACTION_MEMBER_REMOVED => $source === 'server_changed'
                ? "исключён из КП «{$party->name}» из-за смены сервера"
                : "исключён из КП «{$party->name}»",
            default => "изменил состав КП «{$party->name}»",
        };

        return "Персонаж {$subject->name} {$description}. Инициатор: {$actor->name}.";
    }
}
