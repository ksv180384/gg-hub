<?php

namespace Domains\Tag\Actions;

use App\Models\User;
use Domains\Tag\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class ListTagsAction
{
    /**
     * @return Collection<int, Tag>
     */
    public function __invoke(
        bool $includeHidden = false,
        ?User $user = null,
        bool $bypassPickerScope = false,
        ?int $guildIdForPicker = null,
    ): Collection {
        $query = Tag::query()->with(['usedByUser', 'createdByUser'])->orderBy('name');
        if (! $includeHidden) {
            $query->where('is_hidden', false);
        }
        if (! $bypassPickerScope && $user !== null) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId, $guildIdForPicker) {
                $q->where('used_by_user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('used_by_user_id')
                            ->whereNull('used_by_guild_id');
                    });
                if ($guildIdForPicker !== null) {
                    $q->orWhere('used_by_guild_id', $guildIdForPicker);
                }
            });
        }

        return $query->get();
    }
}
