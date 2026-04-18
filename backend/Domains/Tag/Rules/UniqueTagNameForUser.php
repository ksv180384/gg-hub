<?php

namespace Domains\Tag\Rules;

use Closure;
use Domains\Tag\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Один пользователь не может иметь два «своих» тега с тем же названием (без учёта регистра, после trim).
 */
final class UniqueTagNameForUser implements ValidationRule
{
    public function __construct(
        private ?int $usedByUserId,
        private ?int $ignoreTagId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->usedByUserId === null) {
            return;
        }

        $name = is_string($value) ? trim($value) : '';
        if ($name === '') {
            return;
        }

        $normalized = mb_strtolower($name, 'UTF-8');

        $query = Tag::query()
            ->where('used_by_user_id', $this->usedByUserId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized]);

        if ($this->ignoreTagId !== null) {
            $query->where('id', '!=', $this->ignoreTagId);
        }

        if ($query->exists()) {
            $fail('У вас уже есть тег с таким названием.');
        }
    }
}
