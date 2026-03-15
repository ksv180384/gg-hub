<?php

namespace Domains\Post\Enums;

use Illuminate\Support\Facades\Lang;

enum PostStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Draft = 'draft';
    case Hidden = 'hidden';
    case Rejected = 'rejected';
    /** Заблокирован администратором — скрыт из журналов, автор не может редактировать. */
    case Blocked = 'blocked';

    public function label(): string
    {
        return Lang::get("post.status.{$this->value}", [], $this->value);
    }

    /**
     * Человекочитаемая подпись для значения статуса или null.
     */
    public static function labelFor(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        $enum = self::tryFrom($status);

        return $enum !== null ? $enum->label() : '—';
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}

