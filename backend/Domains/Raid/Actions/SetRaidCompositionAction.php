<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;
use Domains\Raid\Models\RaidApplication;
use Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
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
    public function __invoke(User $user, Raid $raid, array $members): Raid
    {
        return DB::transaction(function () use ($user, $raid, $members): Raid {
            $lockedRaid = Raid::query()->lockForUpdate()->findOrFail($raid->id);
            $hasMembers = count(array_filter(
                $members,
                fn ($member) => (int) ($member['character_id'] ?? 0) > 0,
            )) > 0;

            if ($hasMembers && $lockedRaid->children()->exists()) {
                throw ValidationException::withMessages([
                    'members' => ['Рейд с дочерними рейдами не может иметь прикреплённых участников. Сначала перенесите или удалите дочерние рейды.'],
                ]);
            }

            $guildCharacterIds = $lockedRaid->guild->members()->pluck('character_id')->flip();
            $previousCharacterIds = $lockedRaid->members()
                ->pluck('characters.id')
                ->map(fn ($characterId) => (int) $characterId)
                ->all();

            $syncData = [];
            foreach ($members as $item) {
                $characterId = (int) ($item['character_id'] ?? 0);
                $slotIndex = isset($item['slot_index']) ? (int) $item['slot_index'] : null;
                if ($characterId > 0 && $guildCharacterIds->has($characterId)) {
                    $syncData[$characterId] = ['slot_index' => $slotIndex];
                }
            }

            $currentCharacterIds = array_map('intval', array_keys($syncData));
            $removedCharacterIds = array_values(array_diff($previousCharacterIds, $currentCharacterIds));
            $addedCharacterIds = array_values(array_diff($currentCharacterIds, $previousCharacterIds));

            $lockedRaid->members()->sync($syncData);

            if ($removedCharacterIds !== []) {
                $lockedRaid->applications()
                    ->whereIn('character_id', $removedCharacterIds)
                    ->where('status', RaidApplication::STATUS_ACCEPTED)
                    ->update([
                        'status' => RaidApplication::STATUS_REMOVED,
                        'decided_by' => $user->id,
                        'decided_at' => now(),
                    ]);
            }

            if ($addedCharacterIds !== []) {
                $lockedRaid->applications()
                    ->whereIn('character_id', $addedCharacterIds)
                    ->whereIn('status', [
                        RaidApplication::STATUS_PENDING,
                        RaidApplication::STATUS_REJECTED,
                        RaidApplication::STATUS_REMOVED,
                    ])
                    ->update([
                        'status' => RaidApplication::STATUS_ACCEPTED,
                        'decided_by' => $user->id,
                        'decided_at' => now(),
                    ]);
            }

            return $lockedRaid->load(['members:id,name']);
        });
    }
}
