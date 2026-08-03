<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class AdminCharacterFilter extends Filter
{
    public const KEYS_TO_INT = ['game_id', 'server_id'];

    protected function name(string $value): Builder
    {
        $value = trim($value);

        return $value === ''
            ? $this->builder
            : $this->builder->where('characters.name', 'like', "%{$value}%");
    }

    protected function email(string $value): Builder
    {
        $value = trim($value);

        return $value === ''
            ? $this->builder
            : $this->builder->where('users.email', 'like', "%{$value}%");
    }

    protected function gameId(int $value): Builder
    {
        return $this->builder->where('characters.game_id', $value);
    }

    protected function serverId(int $value): Builder
    {
        return $this->builder->where('characters.server_id', $value);
    }
}
