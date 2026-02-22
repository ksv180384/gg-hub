<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Http\Requests\Guild\UpdateGuildRequest;
use App\Services\GuildLogoService;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Illuminate\Http\UploadedFile;
use Stevebauman\Purify\Facades\Purify;

class UpdateGuildAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository,
        private GuildLogoService $guildLogoService
    ) {}

    public function __invoke(Guild $guild, UpdateGuildRequest $request): Guild
    {
        $data = $request->validated();

        $user = $request->user();
        $isOwner = $user && (int) $guild->owner_id === (int) $user->id;
        if (!$isOwner) {
            $data = array_intersect_key($data, array_flip(['is_recruiting']));
        }

        if (array_key_exists('about_text', $data) && $data['about_text'] !== null) {
            $data['about_text'] = Purify::config('guild_rich_text')->clean($data['about_text']);
        }
        if (array_key_exists('charter_text', $data) && $data['charter_text'] !== null) {
            $data['charter_text'] = Purify::config('guild_rich_text')->clean($data['charter_text']);
        }

        if ($request->boolean('remove_logo')) {
            $this->guildLogoService->delete($guild);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            /** @var UploadedFile $file */
            $file = $request->file('logo');
            $data['logo_path'] = $this->guildLogoService->store($file, $guild);
        }

        if (isset($data['server_id'])) {
            $server = Server::query()->findOrFail((int) $data['server_id']);
            $data['game_id'] = $server->game_id;
            $data['localization_id'] = $server->localization_id;
        }

        unset($data['logo'], $data['remove_logo']);
        if (array_key_exists('tag_ids', $data)) {
            $tagIds = is_array($data['tag_ids']) ? array_map('intval', $data['tag_ids']) : [];
            $guild->tags()->sync(array_filter($tagIds));
            unset($data['tag_ids']);
        }
        $logoWasReplaced = $request->hasFile('logo');
        $guild = $this->guildRepository->update($guild, $data);
        if ($logoWasReplaced) {
            $guild->touch();
        }
        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader', 'tags']);
        return $guild;
    }
}
