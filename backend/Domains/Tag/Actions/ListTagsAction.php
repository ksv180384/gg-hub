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
        bool $bypassPickerScope = false
    ): Collection {
        $query = Tag::query()->with('createdBy')->orderBy('name');
        if (! $includeHidden) {
            $query->where('is_hidden', false);
        }
        if (! $bypassPickerScope && $user !== null) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId) {
                $q->where('created_by_user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('created_by_user_id')
                            ->whereNull('created_by_guild_id');
                    });
            });
        }

        return $query->get();
    }
}
