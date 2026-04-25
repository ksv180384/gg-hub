<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class GuildApplicationFilter extends Filter
{
    /**
     * Фильтрация по статусу заявки.
     */
    protected function status(string $value): Builder
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->builder;
        }

        return $this->builder->where('status', $trimmed);
    }

    /**
     * Фильтрация по имени персонажа (подстрока).
     */
    protected function characterName(string $value): Builder
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->builder;
        }

        return $this->builder->whereHas('character', fn (Builder $q) => $q->where('name', 'like', '%' . $trimmed . '%'));
    }
}

