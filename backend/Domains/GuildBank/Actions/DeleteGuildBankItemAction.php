<?php

namespace Domains\GuildBank\Actions;

use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteGuildBankItemAction
{
    public function __invoke(GuildBankItem $item): void
    {
        $hasActiveGrants = GuildBankItemGrant::query()
            ->where('guild_bank_item_id', $item->id)
            ->exists();

        if ($hasActiveGrants) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Нельзя удалить предмет: у него есть активные выдачи. Сначала отмените выдачи в истории.',
                    'errors' => [
                        'guild_bank_item' => ['Есть активные выдачи этого предмета.'],
                    ],
                ], 422)
            );
        }

        $item->delete();
    }
}

