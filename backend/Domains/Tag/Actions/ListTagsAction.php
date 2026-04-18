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
                if ($guildIdForPicker !== null) {
                    // Пикер для гильдии: только общие теги (обе ссылки NULL)
                    // и теги этой гильдии. Личные теги пользователя сюда не попадают —
                    // к гильдии их привязывать нельзя.
                    $q->where(function ($q2) {
                        $q2->whereNull('used_by_user_id')
                            ->whereNull('used_by_guild_id');
                    })->orWhere('used_by_guild_id', $guildIdForPicker);

                    return;
                }

                // Обычный пикер (персонажи и т.п.): свои личные теги + общие.
                $q->where('used_by_user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('used_by_user_id')
                            ->whereNull('used_by_guild_id');
                    });
            });
        }

        return $query->get();
    }
}
