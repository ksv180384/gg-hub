<?php

namespace Domains\Access\Actions;

use Domains\Access\Models\GuildRole;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class DeleteGuildRoleAction
{
    private const PROTECTED_SLUGS = ['leader', 'novice'];

    /**
     * Удаляет роль гильдии. Роли «Лидер» и «Новичок» удалять нельзя.
     */
    public function __invoke(GuildRole $guildRole): void
    {
        if (in_array($guildRole->slug, self::PROTECTED_SLUGS, true)) {
            throw new UnprocessableEntityHttpException(
                'Роли «Лидер» и «Новичок» удалять нельзя.'
            );
        }

        $guildRole->delete();
    }
}
