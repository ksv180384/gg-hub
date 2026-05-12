<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class GuildDkpLedgerFilter extends Filter
{
    public const KEYS_TO_DATE = [
        'occurred_from',
        'occurred_to',
    ];

    public const KEYS_TO_INT = [
        'event_history_title_id',
    ];

    /**
     * Нижняя граница периода по дате операции.
     */
    protected function occurredFrom(CarbonImmutable $value): Builder
    {
        return $this->builder->where('occurred_at', '>=', $value->startOfDay());
    }

    /**
     * Верхняя граница периода по дате операции.
     */
    protected function occurredTo(CarbonImmutable $value): Builder
    {
        return $this->builder->where('occurred_at', '<=', $value->endOfDay());
    }

    /**
     * Фильтрация по нику пользователя, которому начислены или списаны очки.
     */
    protected function userName(string $value): Builder
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->builder;
        }

        return $this->builder->whereHas('user', fn (Builder $query) => $query->where('name', 'like', '%' . $trimmed . '%'));
    }

    /**
     * Фильтрация по виду события, за которое начислены очки.
     */
    protected function eventHistoryTitleId(int $value): Builder
    {
        if ($value <= 0) {
            return $this->builder;
        }

        return $this->builder->whereHas(
            'eventHistory',
            fn (Builder $query) => $query->where('event_history_title_id', $value),
        );
    }

    /**
     * Фильтрация по источнику движения ДКП.
     */
    protected function source(string $value): Builder
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->builder;
        }

        return $this->builder->where('source', $trimmed);
    }
}
