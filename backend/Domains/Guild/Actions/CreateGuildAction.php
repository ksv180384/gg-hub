<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Models\User;
use Domains\Access\Models\GuildRole;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Support\Str;

class CreateGuildAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(User $user, array $data): Guild
    {
        $server = Server::query()->findOrFail((int) $data['server_id']);
        $data['owner_id'] = $user->id;
        $data['game_id'] = $server->game_id;
        $data['localization_id'] = $server->localization_id;
        $data['slug'] = isset($data['slug']) && \trim((string) $data['slug']) !== ''
            ? Str::slug($data['slug'])
            : Str::slug($data['name']);
        $leaderCharacterId = (int) $data['leader_character_id'];
        $data['leader_character_id'] = $leaderCharacterId;
        $guild = $this->guildRepository->create($data);

        $leaderRole = GuildRole::query()->create([
            'guild_id' => $guild->id,
            'name' => 'Лидер',
            'slug' => 'leader',
            'priority' => 1000,
        ]);
        GuildMember::query()->create([
            'guild_id' => $guild->id,
            'character_id' => $leaderCharacterId,
            'guild_role_id' => $leaderRole->id,
            'joined_at' => now(),
        ]);

        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader']);
        return $guild;
    }
}
