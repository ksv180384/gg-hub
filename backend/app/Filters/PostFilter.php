<?php

namespace App\Filters;

use App\Core\Filters\Filter;
use Domains\Post\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;

class PostFilter extends Filter
{
    public const KEYS_TO_INT = ['guild_id', 'game_id'];

    protected function filter(string $value): Builder
    {
        if ($value !== 'pending_global') {
            return $this->builder;
        }

        return $this->builder
            ->where('status_global', PostStatus::Pending->value)
            ->where(function (Builder $query): void {
                $query->whereNull('status_guild')
                    ->orWhere('status_guild', '!=', PostStatus::Pending->value);
            });
    }

    protected function scope(string $value): Builder
    {
        return match ($value) {
            'global' => $this->builder->where('is_visible_global', true),
            'guild' => $this->builder->whereNotNull('guild_id'),
            default => $this->builder,
        };
    }

    protected function guildId(int $value): Builder
    {
        return $this->request->input('scope') === 'guild'
            ? $this->builder->where('guild_id', $value)
            : $this->builder;
    }

    protected function gameId(int $value): Builder
    {
        return $this->builder->where('game_id', $value);
    }

    protected function status(string $value): Builder
    {
        if (! in_array($value, PostStatus::values(), true)) {
            return $this->builder;
        }

        return match ($this->request->input('scope')) {
            'global' => $this->builder->where('status_global', $value),
            'guild' => $this->builder->where('status_guild', $value),
            default => $this->builder,
        };
    }

    protected function q(string $value): Builder
    {
        $value = trim($value);

        return $value === ''
            ? $this->builder
            : $this->builder->where('title', 'like', "%{$value}%");
    }
}
