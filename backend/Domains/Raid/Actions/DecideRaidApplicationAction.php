<?php

namespace Domains\Raid\Actions;

use App\Actions\Notification\CreateRaidApplicationNotificationAction;
use Domains\Raid\Models\Raid;
use Domains\Raid\Models\RaidApplication;
use Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideRaidApplicationAction
{
    private const MAX_SLOTS = 1000;

    public function __construct(
        private CreateRaidApplicationNotificationAction $notificationAction,
    ) {}

    public function __invoke(
        User $user,
        Raid $raid,
        RaidApplication $application,
        bool $accept,
    ): RaidApplication {
        $decided = DB::transaction(function () use ($user, $raid, $application, $accept) {
            $locked = RaidApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ((int) $locked->raid_id !== (int) $raid->id) {
                abort(404);
            }

            if ($locked->status !== RaidApplication::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'application' => ['Эта заявка уже рассмотрена.'],
                ]);
            }

            if ($accept) {
                $lockedRaid = Raid::query()->lockForUpdate()->findOrFail($raid->id);

                if ($lockedRaid->members()->whereKey($locked->character_id)->exists()) {
                    throw ValidationException::withMessages([
                        'application' => ['Персонаж уже состоит в этом рейде.'],
                    ]);
                }

                $occupiedSlots = $lockedRaid->members()
                    ->wherePivotNotNull('slot_index')
                    ->pluck('raid_members.slot_index')
                    ->map(fn ($slot) => (int) $slot)
                    ->flip();
                $slotIndex = null;
                for ($index = 0; $index < self::MAX_SLOTS; $index++) {
                    if (! $occupiedSlots->has($index)) {
                        $slotIndex = $index;
                        break;
                    }
                }

                if ($slotIndex === null) {
                    throw ValidationException::withMessages([
                        'application' => ['В рейде нет свободных ячеек.'],
                    ]);
                }

                $lockedRaid->members()->attach($locked->character_id, [
                    'slot_index' => $slotIndex,
                    'accepted_at' => now(),
                ]);
            }

            $locked->update([
                'status' => $accept
                    ? RaidApplication::STATUS_ACCEPTED
                    : RaidApplication::STATUS_REJECTED,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            return $locked;
        });

        $this->notificationAction->decided($decided);

        return $decided;
    }
}
