<?php

namespace Domains\Post\Enums;

enum PostStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Draft = 'draft';
    case Hidden = 'hidden';
    case Rejected = 'rejected';

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

