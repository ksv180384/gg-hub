<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;
use Illuminate\Support\Collection;

class ListRaidDescendantUsersAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(Raid $root): Collection
    {
        $allRaids = Raid::query()
            ->where('guild_id', $root->guild_id)
            ->get(['id', 'parent_id']);
        $byParent = $allRaids->groupBy('parent_id');
        $descendantIds = collect();
        $queue = [$root->id];

        while ($queue !== []) {
            $parentId = array_shift($queue);
            foreach ($byParent->get($parentId, collect()) as $child) {
                $descendantIds->push($child->id);
                $queue[] = $child->id;
            }
        }

        if ($descendantIds->isEmpty()) {
            return collect();
        }

        $raids = Raid::query()
            ->whereIn('id', $descendantIds)
            ->with([
                'members.user:id,name',
                'members.gameClasses:id,name,name_ru,slug,image',
                'members.characterGuildTags' => fn ($query) => $query->wherePivot('guild_id', $root->guild_id),
                'members.tags',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $users = [];
        foreach ($raids as $raid) {
            foreach ($raid->members as $character) {
                $userId = (int) $character->user_id;
                $users[$userId] ??= [
                    'user_id' => $userId,
                    'user_name' => $character->user?->name,
                    'characters' => [],
                ];
                $users[$userId]['characters'][$character->id] ??= [
                    'id' => $character->id,
                    'name' => $character->name,
                    'game_classes' => $character->gameClasses->map(fn ($class) => [
                        'id' => $class->id,
                        'name' => $class->name,
                        'name_ru' => $class->name_ru,
                        'slug' => $class->slug,
                    ])->values()->all(),
                    'tags' => $character->characterGuildTags->concat($character->tags)
                        ->unique('id')
                        ->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ])->values()->all(),
                    'raids' => [],
                ];
                $users[$userId]['characters'][$character->id]['raids'][] = [
                    'id' => $raid->id,
                    'name' => $raid->name,
                ];
            }
        }

        return collect($users)->map(function (array $user) {
            $user['characters'] = array_values($user['characters']);

            return $user;
        })->values();
    }
}
