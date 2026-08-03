<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class GuildFilter extends Filter
{
    public const KEYS_TO_BOOL = ['is_recruiting'];

    public const KEYS_TO_INT = ['game_id', 'localization_id', 'server_id'];

    public const KEYS_TO_ARRAY = ['localization_ids', 'server_ids'];

    protected function name(string $value): Builder
    {
        $value = trim($value);

        return $value === ''
            ? $this->builder
            : $this->builder->where('name', 'like', "%{$value}%");
    }

    protected function gameId(int $value): Builder
    {
        return $this->builder->where('game_id', $value);
    }

    protected function localizationId(int $value): Builder
    {
        return $this->builder->where('localization_id', $value);
    }

    protected function serverId(int $value): Builder
    {
        return $this->builder->where('server_id', $value);
    }

    /**
     * @param  array<int, int>  $value
     */
    protected function localizationIds(array $value): Builder
    {
        $ids = array_filter(array_map('intval', $value));

        return $ids === []
            ? $this->builder
            : $this->builder->whereIn('localization_id', $ids);
    }

    /**
     * @param  array<int, int>  $value
     */
    protected function serverIds(array $value): Builder
    {
        $ids = array_filter(array_map('intval', $value));

        return $ids === []
            ? $this->builder
            : $this->builder->whereIn('server_id', $ids);
    }

    protected function isRecruiting(bool $value): Builder
    {
        return $this->builder->where('is_recruiting', $value);
    }
}
