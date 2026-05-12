<?php

namespace Domains\GuildBank\Actions;

use App\Models\User;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Actions\GetGuildUserDkpBalanceAction;

class GetGuildBankPageContextAction
{
    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction,
        private GetGuildUserDkpBalanceAction $getGuildUserDkpBalanceAction,
    ) {}

    /** @return array{my_permission_slugs: list<string>, dkp_enabled: bool, dkp_ledger_available: bool, my_dkp_balance: int|null} */
    public function __invoke(Guild $guild, User $user): array
    {
        $dkpEnabled = (bool) ($guild->dkp_enabled ?? false);

        return [
            'my_permission_slugs' => ($this->getUserGuildPermissionSlugsAction)($user, $guild)->all(),
            'dkp_enabled' => $dkpEnabled,
            'dkp_ledger_available' => $dkpEnabled,
            'my_dkp_balance' => $dkpEnabled
                ? ($this->getGuildUserDkpBalanceAction)($guild, (int) $user->id)
                : null,
        ];
    }
}
