<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Http\Requests\Guild\UpdateGuildRequest;
use App\Services\GuildLogoService;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Illuminate\Http\UploadedFile;

class UpdateGuildAction
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository,
        private GuildLogoService $guildLogoService
    ) {}

    public function __invoke(Guild $guild, UpdateGuildRequest $request): Guild
    {
        $data = $request->validated();

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
        $guild = $this->guildRepository->update($guild, $data);
        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader']);
        return $guild;
    }
}
