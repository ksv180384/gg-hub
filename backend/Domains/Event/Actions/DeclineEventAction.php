<?php

namespace Domains\Event\Actions;

use App\Models\User;
use App\Services\GuildEventSocketBroadcaster;
use Domains\Event\Models\Event;
use Domains\Event\Models\EventParticipant;
use Domains\Guild\Models\GuildMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeclineEventAction
{
    public function __construct(
        private readonly GuildEventSocketBroadcaster $broadcaster
    ) {}

    public function __invoke(User $user, Event $event, ?int $characterId = null): Event
    {
        $characterId = $characterId ?: $this->pickUserCharacterIdForGuild($user, (int) $event->guild_id);

        if ($characterId === null) {
            throw ValidationException::withMessages([
                'character_id' => 'Не удалось определить персонажа пользователя в гильдии для этого события.',
            ]);
        }

        DB::transaction(function () use ($event, $characterId) {
            $existing = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('character_id', $characterId)
                ->first();

            if ($existing) {
                $existing->delete();
                return;
            }

            EventParticipant::query()->create([
                'event_id' => $event->id,
                'character_id' => $characterId,
            ]);
        });

        $event->loadMissing(['participants.character:id,name,user_id']);
        $this->broadcaster->broadcastChangedFor((int) $event->guild_id, (int) $event->id);

        return $event;
    }

    private function pickUserCharacterIdForGuild(User $user, int $guildId): ?int
    {
        $row = GuildMember::query()
            ->select('guild_members.character_id')
            ->join('characters', 'characters.id', '=', 'guild_members.character_id')
            ->where('guild_members.guild_id', $guildId)
            ->where('characters.user_id', $user->id)
            ->orderByDesc('characters.is_main')
            ->orderBy('guild_members.joined_at')
            ->first();

        return $row?->character_id ? (int) $row->character_id : null;
    }
}

