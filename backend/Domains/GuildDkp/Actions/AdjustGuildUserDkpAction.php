<?php

namespace Domains\GuildDkp\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdjustGuildUserDkpAction
{
    public function __construct(
        private RecordGuildDkpLedgerEntryAction $recordGuildDkpLedgerEntryAction,
    ) {}

    /** @param array{amount:int,reason?:?string,character_id?:?int} $data */
    public function __invoke(Guild $guild, int $userId, User $actor, array $data): GuildDkpLedgerEntry
    {
        if (! (bool) ($guild->dkp_enabled ?? false)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Система ДКП отключена в этой гильдии.',
                'errors' => [
                    'amount' => ['Система ДКП отключена в этой гильдии.'],
                ],
            ], 422));
        }

        $amount = (int) $data['amount'];
        if ($amount === 0) {
            throw new HttpResponseException(response()->json([
                'message' => 'Укажите ненулевое изменение ДКП.',
                'errors' => [
                    'amount' => ['Укажите ненулевое изменение ДКП.'],
                ],
            ], 422));
        }

        return ($this->recordGuildDkpLedgerEntryAction)($guild, [
            'user_id' => $userId,
            'amount' => $amount,
            'source' => GuildDkpLedgerSource::Manual,
            'character_id' => $data['character_id'] ?? null,
            'actor_user_id' => $actor->id,
            'reason' => $data['reason'] ?? null,
        ]);
    }
}
