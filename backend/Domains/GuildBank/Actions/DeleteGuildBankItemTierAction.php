<?php

namespace Domains\GuildBank\Actions;

use Domains\GuildBank\Models\GuildBankItemTier;
use Illuminate\Validation\ValidationException;

class DeleteGuildBankItemTierAction
{
    public function __invoke(GuildBankItemTier $tier): void
    {
        if ($tier->items()->exists()) {
            throw ValidationException::withMessages([
                'tier' => ['Нельзя удалить тир: к нему привязаны предметы.'],
            ]);
        }

        $tier->delete();
    }
}
