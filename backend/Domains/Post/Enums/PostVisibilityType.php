<?php

namespace Domains\Post\Enums;

enum PostVisibilityType: string
{
    case Anonymous = 'anonymous';
    case Guild = 'guild';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}

