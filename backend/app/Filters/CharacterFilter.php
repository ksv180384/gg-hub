<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use App\Http\Requests\Character\CharacterFilterRequest;
use Illuminate\Database\Eloquent\Builder;

class CharacterFilter extends Filter
{
    public const KEYS_TO_ARRAY = ['localization_ids', 'server_ids', 'game_class_ids'];

    /**
     * Фильтрация по имени персонажа.
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
     * Фильтрация по классам: персонаж должен иметь все выбранные классы
     * (если выбран один класс — персонажи с этим классом; если два — с обоими и т.д.).
     *
     * @param array<int, int> $value
     */
    protected function gameClassIds(array $value): Builder
    {
        $ids = array_values(array_filter(array_map('intval', $value)));
        if ($ids === []) {
            return $this->builder;
        }

        foreach ($ids as $gameClassId) {
            $this->builder->whereHas('gameClasses', fn (Builder $q) => $q->where('game_class_id', $gameClassId));
        }

        return $this->builder;
    }
}
