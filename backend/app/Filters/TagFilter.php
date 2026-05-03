<?php

declare(strict_types=1);

namespace App\Filters;

use App\Core\Filters\Filter;
use App\Http\Requests\Tag\TagListFilterRequest;
use App\Models\User;
use Domains\Guild\Models\GuildMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * Единый фильтр списка тегов.
 *
 * Сам вычисляет контекст из {@see TagListFilterRequest} (текущий пользователь, полный
 * админский список, опциональный `guild_id` для пикера) и поверх этого применяет
 * admin-поля query (kind, tag_name, guild_name, user_name).
 */
class TagFilter extends Filter
{
    private const PERMISSION_ADMIN = 'admnistrirovanie';

    private readonly ?User $user;

    private readonly bool $adminFullList;

    private readonly ?int $guildIdForPicker;

    public function __construct(TagListFilterRequest $request)
    {
        parent::__construct($request);

        $requestUser = $request->user();
        $this->user = $requestUser instanceof User ? $requestUser : null;

        $this->adminFullList = $request->boolean('include_hidden')
            && $this->user !== null
            && in_array(self::PERMISSION_ADMIN, $this->user->getAllPermissionSlugs(), true);

        $this->guildIdForPicker = $this->resolveGuildIdForPicker($request);
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        $this->applyVisibility();
        $this->applyPickerScope();

        if ($this->adminFullList) {
            // Подхватывает kind/tag_name/guild_name/user_name из FormRequest через базовый Filter::apply().
            parent::apply($this->builder);
        }

        return $this->builder;
    }

    private function applyVisibility(): void
    {
        if (! $this->adminFullList) {
            $this->builder->where('is_hidden', false);
        }
    }

    private function applyPickerScope(): void
    {
        if ($this->adminFullList || $this->user === null) {
            return;
        }

        $userId = $this->user->id;
        $guildIdForPicker = $this->guildIdForPicker;

        $this->builder->where(function ($q) use ($userId, $guildIdForPicker) {
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

    private function resolveGuildIdForPicker(TagListFilterRequest $request): ?int
    {
        if (! $request->filled('guild_id') || $this->user === null) {
            return null;
        }

        $guildId = (int) $request->query('guild_id');
        $isMember = GuildMember::query()
            ->where('guild_id', $guildId)
            ->whereHas('character', fn ($q) => $q->where('user_id', $this->user->id))
            ->exists();

        return $isMember ? $guildId : null;
    }

    protected function kind(string $value): Builder
    {
        return match ($value) {
            'common' => $this->builder->whereNull('used_by_user_id')->whereNull('used_by_guild_id'),
            'guild' => $this->builder->whereNotNull('used_by_guild_id'),
            'user' => $this->builder->whereNotNull('used_by_user_id'),
            default => $this->builder,
        };
    }

    protected function tagName(string $value): Builder
    {
        $pattern = self::likeContains($value);
        if ($pattern === null) {
            return $this->builder;
        }

        return $this->builder->where('name', 'like', $pattern);
    }

    protected function guildName(string $value): Builder
    {
        $pattern = self::likeContains($value);
        if ($pattern === null) {
            return $this->builder;
        }

        return $this->builder->whereNotNull('used_by_guild_id')
            ->whereHas('usedByGuild', fn (Builder $q) => $q->where('name', 'like', $pattern));
    }

    protected function userName(string $value): Builder
    {
        $pattern = self::likeContains($value);
        if ($pattern === null) {
            return $this->builder;
        }

        return $this->builder->whereNotNull('used_by_user_id')
            ->whereHas('usedByUser', fn (Builder $q) => $q->where('name', 'like', $pattern));
    }

    private static function likeContains(string $term): ?string
    {
        $t = trim($term);
        if ($t === '') {
            return null;
        }
        $t = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $t);

        return '%'.$t.'%';
    }
}
