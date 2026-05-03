<?php

namespace Domains\Tag\Rules;

use Closure;
use Domains\Tag\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * У гильдии не может быть двух тегов с одним названием (used_by_guild_id = эта гильдия).
 */
final class UniqueTagNameForGuild implements ValidationRule
{
    public function __construct(
        private int $usedByGuildId,
        private ?int $ignoreTagId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '') {
            return;
        }

        $normalized = mb_strtolower($name, 'UTF-8');

        $query = Tag::query()
            ->where('used_by_guild_id', $this->usedByGuildId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized]);

        if ($this->ignoreTagId !== null) {
            $query->where('id', '!=', $this->ignoreTagId);
        }

        if ($query->exists()) {
            $fail('У гильдии уже есть тег с таким названием.');
        }
    }
}
