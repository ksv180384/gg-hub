<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;
use Illuminate\Validation\ValidationException;

class SetRaidCompositionAction
{
    /**
     * Устанавливает состав рейда: список участников и их слоты.
     * В рейд можно добавить только персонажей, состоящих в гильдии рейда.
     * Рейд с дочерними рейдами не может иметь участников.
     *
     * @param  array<int, array{character_id: int, slot_index: int|null}>  $members  [ ['character_id' => 1, 'slot_index' => 0], ... ]
     */
    public function __invoke(Raid $raid, array $members): Raid
    {
        $hasMembers = count(array_filter($members, fn ($m) => (int) ($m['character_id'] ?? 0) > 0)) > 0;
        if ($hasMembers && $raid->children()->exists()) {
            throw ValidationException::withMessages([
                'members' => ['Рейд с дочерними рейдами не может иметь прикреплённых участников. Сначала перенесите или удалите дочерние рейды.'],
            ]);
        }

        $guildCharacterIds = $raid->guild->members()->pluck('character_id')->flip();

        $syncData = [];
        foreach ($members as $item) {
            $characterId = (int) ($item['character_id'] ?? 0);
            $slotIndex = isset($item['slot_index']) ? (int) $item['slot_index'] : null;
            if ($characterId > 0 && $guildCharacterIds->has($characterId)) {
                $syncData[$characterId] = ['slot_index' => $slotIndex];
            }
        }

        $raid->members()->sync($syncData);

        return $raid->load(['members:id,name']);
    }
}
