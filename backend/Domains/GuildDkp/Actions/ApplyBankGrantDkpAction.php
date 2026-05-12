<?php

namespace Domains\GuildDkp\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplyBankGrantDkpAction
{
    public function __construct(
        private GetGuildUserDkpBalanceAction $getGuildUserDkpBalanceAction,
        private RecordGuildDkpLedgerEntryAction $recordGuildDkpLedgerEntryAction,
        private ResolveGuildMemberUserIdAction $resolveGuildMemberUserIdAction,
    ) {}

    /**
     * @return array{charged:int,requires_confirmation:bool,balance:int,balance_after:int}
     */
    public function preview(Guild $guild, GuildBankItem $item, int $receivedByCharacterId): array
    {
        $charged = $this->resolveChargeAmount($guild, $item);
        if ($charged <= 0) {
            return [
                'charged' => 0,
                'requires_confirmation' => false,
                'balance' => 0,
                'balance_after' => 0,
            ];
        }

        $userId = ($this->resolveGuildMemberUserIdAction)($guild, $receivedByCharacterId);
        $balance = ($this->getGuildUserDkpBalanceAction)($guild, $userId);
        $balanceAfter = $balance - $charged;

        return [
            'charged' => $charged,
            'requires_confirmation' => $balanceAfter < 0,
            'balance' => $balance,
            'balance_after' => $balanceAfter,
        ];
    }

    public function __invoke(
        Guild $guild,
        GuildBankItem $item,
        GuildBankItemGrant $grant,
        ?User $actor,
        bool $confirmNegativeBalance,
    ): int {
        $charged = $this->resolveChargeAmount($guild, $item);
        if ($charged <= 0) {
            return 0;
        }

        $userId = ($this->resolveGuildMemberUserIdAction)($guild, (int) $grant->received_by_character_id);
        $balance = ($this->getGuildUserDkpBalanceAction)($guild, $userId);
        $balanceAfter = $balance - $charged;

        if ($balanceAfter < 0 && ! $confirmNegativeBalance) {
            throw new HttpResponseException(response()->json([
                'message' => 'Недостаточно ДКП для выдачи предмета.',
                'errors' => [
                    'confirm_negative_balance' => [
                        "У участника {$balance} ДКП, требуется {$charged}. После выдачи баланс станет {$balanceAfter}.",
                    ],
                ],
                'data' => [
                    'requires_confirmation' => true,
                    'balance' => $balance,
                    'charged' => $charged,
                    'balance_after' => $balanceAfter,
                ],
            ], 422));
        }

        ($this->recordGuildDkpLedgerEntryAction)($guild, [
            'user_id' => $userId,
            'amount' => -$charged,
            'occurred_at' => $grant->granted_at,
            'source' => GuildDkpLedgerSource::BankGrant,
            'guild_bank_item_grant_id' => $grant->id,
            'guild_bank_item_id' => $item->id,
            'character_id' => (int) $grant->received_by_character_id,
            'actor_user_id' => $actor?->id,
            'reason' => $grant->reason !== '' ? $grant->reason : null,
        ]);

        return $charged;
    }

    private function resolveChargeAmount(Guild $guild, GuildBankItem $item): int
    {
        if (! (bool) ($guild->dkp_enabled ?? false)) {
            return 0;
        }

        return max(0, (int) ($item->dkp_cost ?? 0));
    }
}
