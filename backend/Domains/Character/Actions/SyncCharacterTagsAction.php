<?php

namespace Domains\Character\Actions;

use App\Models\User;
use Domains\Character\Models\Character;

class SyncCharacterTagsAction
{
    /**
     * Сохраняет набор тегов; для вновь прикреплённых записывает, кто их добавил.
     *
     * @param  list<int|numeric-string>  $tagIds
     */
    public function __invoke(Character $character, array $tagIds, User $user): void
    {
        $newIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $tagIds))));
        $oldIds = $character->tags()->pluck('tags.id')->all();
        $toDetach = array_values(array_diff($oldIds, $newIds));
        $toAttach = array_values(array_diff($newIds, $oldIds));
        if ($toDetach !== []) {
            $character->tags()->detach($toDetach);
        }
        $uid = $user->id;
        foreach ($toAttach as $tagId) {
            $character->tags()->attach($tagId, ['added_by_user_id' => $uid]);
        }
    }
}
