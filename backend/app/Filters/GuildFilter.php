<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use App\Http\Requests\Guild\GuildFilterRequest;
use Illuminate\Database\Eloquent\Builder;

class GuildFilter extends Filter
{
    public const KEYS_TO_BOOL = ['is_recruiting'];
    public const KEYS_TO_INT = ['game_id'];
    public const KEYS_TO_ARRAY = ['localization_ids', 'server_ids'];

    /**
     * Фильтрация по названию гильдии.
     */
    protected function name(string $value): Builder
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->builder;
        }

        return $this->builder->where('name', 'like', '%' . $trimmed . '%');
    }

    /**
     * Фильтрация по игре.
     */
    protected function gameId(int $value): Builder
    {
        return $this->builder->where('game_id', $value);
    }

    /**
     * Фильтрация по списку локализаций.
     *
     * @param array<int, int> $value
     */
    protected function localizationIds(array $value): Builder
    {
        $ids = array_filter(array_map('intval', $value));
        if ($ids === []) {
            return $this->builder;
        }

        return $this->builder->whereIn('localization_id', $ids);
    }

    /**
     * Фильтрация по списку серверов.
     *
     * @param array<int, int> $value
     */
    protected function serverIds(array $value): Builder
    {
        $ids = array_filter(array_map('intval', $value));
        if ($ids === []) {
            return $this->builder;
        }

        return $this->builder->whereIn('server_id', $ids);
    }

    /**
     * Фильтрация по открытому набору в гильдию.
     */
    protected function isRecruiting(bool $value): Builder
    {
        return $this->builder->where('is_recruiting', $value);
    }
}
