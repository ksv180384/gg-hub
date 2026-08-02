<?php

namespace Domains\Raid\Actions;

use App\Actions\Notification\CreateRaidApplicationNotificationAction;
use Domains\Character\Models\Character;
use Domains\Raid\Models\Raid;
use Domains\Raid\Models\RaidApplication;
use Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitRaidApplicationAction
{
    public function __construct(
        private CreateRaidApplicationNotificationAction $notificationAction,
    ) {}

    public function __invoke(User $user, Raid $raid, int $characterId): RaidApplication
    {
        if (! $raid->is_recruiting) {
            throw ValidationException::withMessages([
                'raid' => ['Набор в этот рейд закрыт.'],
            ]);
        }

        if ($raid->children()->exists()) {
            throw ValidationException::withMessages([
                'raid' => ['Подать заявку можно только в рейд без дочерних рейдов.'],
            ]);
        }

        $character = Character::query()
            ->whereKey($characterId)
            ->where('user_id', $user->id)
            ->whereHas('guildMember', fn ($query) => $query->where('guild_id', $raid->guild_id))
            ->first();

        if (! $character) {
            throw ValidationException::withMessages([
                'character_id' => ['Выбранный персонаж не принадлежит вам или не состоит в этой гильдии.'],
            ]);
        }

        if ($raid->members()->whereKey($character->id)->exists()) {
            throw ValidationException::withMessages([
                'character_id' => ['Этот персонаж уже состоит в рейде.'],
            ]);
        }

        $application = DB::transaction(function () use ($raid, $character): RaidApplication {
            $existing = RaidApplication::query()
                ->where('raid_id', $raid->id)
                ->where('character_id', $character->id)
                ->lockForUpdate()
                ->first();

            $canReapply = $existing && in_array($existing->status, [
                RaidApplication::STATUS_REJECTED,
                RaidApplication::STATUS_REMOVED,
            ], true);

            if ($existing && ! $canReapply) {
                throw ValidationException::withMessages([
                    'character_id' => ['Этот персонаж уже подавал заявку в данный рейд.'],
                ]);
            }

            if ($existing) {
                $existing->update([
                    'status' => RaidApplication::STATUS_PENDING,
                    'decided_by' => null,
                    'decided_at' => null,
                    'created_at' => now(),
                ]);

                return $existing;
            }

            return $raid->applications()->create([
                'character_id' => $character->id,
                'status' => RaidApplication::STATUS_PENDING,
            ]);
        });
        $application->setRelation('character', $character);
        $this->notificationAction->submitted($application);

        return $application;
    }
}
