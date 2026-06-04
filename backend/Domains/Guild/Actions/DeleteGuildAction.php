<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DeleteGuildAction
{
    public function __invoke(User $user, Guild $guild): void
    {
        if (!$this->isLeaderOwner($user, $guild)) {
            $this->deny('Удалить гильдию может только текущий лидер.');
        }

        if (!$this->hasOnlyLeaderMember($guild)) {
            $this->deny('Удалить гильдию можно только когда в ней остался один участник — лидер гильдии.');
        }

        DB::transaction(function () use ($guild): void {
            GuildMember::query()
                ->where('guild_id', $guild->id)
                ->delete();

            $guild->delete();
        });
    }

    public function canDelete(Guild $guild, ?User $user = null): bool
    {
        if (!$user || !$this->isLeaderOwner($user, $guild)) {
            return false;
        }

        return $this->hasOnlyLeaderMember($guild);
    }

    private function isLeaderOwner(User $user, Guild $guild): bool
    {
        if (!$guild->leader_character_id) {
            return false;
        }

        $leaderCharacter = Character::query()->find($guild->leader_character_id);

        return $leaderCharacter !== null
            && (int) $leaderCharacter->user_id === (int) $user->id;
    }

    private function hasOnlyLeaderMember(Guild $guild): bool
    {
        if (!$guild->leader_character_id) {
            return false;
        }

        $membersCount = (int) ($guild->members_count ?? $guild->members()->count());
        if ($membersCount !== 1) {
            return false;
        }

        return GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $guild->leader_character_id)
            ->exists();
    }

    private function deny(string $message): void
    {
        throw new HttpResponseException(response()->json(['message' => $message], 403));
    }
}
