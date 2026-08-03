<?php

namespace Domains\ConstantParty\Actions;

use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\Notification\Models\Notification;
use Illuminate\Support\Facades\DB;

class DissolveConstantPartyAction
{
    public function __invoke(ConstantParty $party, ConstantPartyMember $leader): void
    {
        $leader->loadMissing('character');

        DB::transaction(function () use ($party, $leader): void {
            /** @var ConstantParty $lockedParty */
            $lockedParty = ConstantParty::query()
                ->whereKey($party->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedLeader = ConstantPartyMember::query()
                ->whereKey($leader->id)
                ->where('constant_party_id', $lockedParty->id)
                ->where('role', ConstantPartyMember::ROLE_LEADER)
                ->with('character')
                ->lockForUpdate()
                ->first();

            if (! $lockedLeader) {
                abort(403);
            }

            $recipientUserIds = ConstantPartyMember::query()
                ->join('characters', 'characters.id', '=', 'constant_party_members.character_id')
                ->where('constant_party_members.constant_party_id', $lockedParty->id)
                ->distinct()
                ->pluck('characters.user_id');

            $message = "КП «{$lockedParty->name}» распущена. "
                ."Инициатор: {$lockedLeader->character->name}. "
                .'Хранилище, история выдач, чат, приглашения и логи КП удалены без возможности восстановления.';

            foreach ($recipientUserIds as $userId) {
                Notification::query()->create([
                    'user_id' => $userId,
                    'message' => $message,
                    'link' => '/my-constant-parties',
                ]);
            }

            $lockedParty->forceDelete();
        });
    }
}
