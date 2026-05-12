<?php

namespace Domains\GuildBank\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Actions\ApplyBankGrantDkpAction;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CreateGuildBankItemGrantAction
{
    public function __construct(
        private ApplyBankGrantDkpAction $applyBankGrantDkpAction,
    ) {}

    /** @param array{guild_bank_item_id:int,received_by_character_id:int,granted_by_character_id?:?int,reason?:?string,granted_at?:?string,confirm_negative_balance?:bool} $data */
    public function __invoke(Guild $guild, array $data, ?User $actor = null): GuildBankItemGrant
    {
        return DB::transaction(function () use ($guild, $data, $actor): GuildBankItemGrant {
            /** @var GuildBankItem $item */
            $item = GuildBankItem::query()
                ->where('guild_id', $guild->id)
                ->where('id', $data['guild_bank_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->quantity !== null && (int) $item->quantity <= 0) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Недостаточно предметов на складе для выдачи.',
                        'errors' => [
                            'guild_bank_item_id' => ['Недостаточно предметов на складе для выдачи.'],
                        ],
                    ], 422)
                );
            }

            $reason = isset($data['reason']) ? trim((string) $data['reason']) : '';

            $grant = new GuildBankItemGrant();
            $grant->fill([
                'guild_id' => $guild->id,
                'guild_bank_item_id' => $data['guild_bank_item_id'],
                'received_by_character_id' => $data['received_by_character_id'],
                'granted_by_character_id' => $data['granted_by_character_id'] ?? null,
                'reason' => $reason,
                'granted_at' => ! empty($data['granted_at']) ? $data['granted_at'] : CarbonImmutable::now(),
            ]);
            $grant->save();

            $charged = ($this->applyBankGrantDkpAction)(
                $guild,
                $item,
                $grant,
                $actor,
                (bool) ($data['confirm_negative_balance'] ?? false),
            );
            if ($charged > 0) {
                $grant->dkp_charged = $charged;
                $grant->save();
            }

            if ($item->quantity !== null) {
                $item->quantity = max(0, (int) $item->quantity - 1);
                $item->save();
            }

            return $grant;
        });
    }
}
