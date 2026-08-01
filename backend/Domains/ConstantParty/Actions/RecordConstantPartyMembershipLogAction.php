<?php

namespace Domains\ConstantParty\Actions;

use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyStorageLog;

class RecordConstantPartyMembershipLogAction
{
    /** @param array<string, mixed>|null $metadata */
    public function __invoke(
        ConstantParty $party,
        string $action,
        int $actorCharacterId,
        int $memberCharacterId,
        ?array $metadata = null,
    ): void {
        $characterNames = Character::query()
            ->whereIn('id', array_unique([$actorCharacterId, $memberCharacterId]))
            ->pluck('name', 'id');

        $memberName = $characterNames->get($memberCharacterId, 'Персонаж');

        ConstantPartyStorageLog::query()->create([
            'constant_party_id' => $party->id,
            'item_id' => null,
            'actor_character_id' => $actorCharacterId,
            'recipient_character_id' => $memberCharacterId,
            'action' => $action,
            'item_name' => $memberName,
            'actor_character_name' => $characterNames->get($actorCharacterId, 'Персонаж'),
            'recipient_character_name' => $memberName,
            'metadata' => $metadata,
        ]);
    }
}
