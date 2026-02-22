<?php

namespace App\Actions\Server;

use App\Models\Game;
use App\Models\Localization;
use App\Models\Server;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MergeServersAction
{
    /**
     * @return array{message: string, target_server_id: int}
     */
    public function __invoke(Game $game, Localization $localization, int $targetServerId, array $sourceServerIds): array
    {
        if ($localization->game_id !== (int) $game->id) {
            throw new HttpException(404, 'Локализация не принадлежит этой игре.');
        }
        if (in_array($targetServerId, $sourceServerIds, true)) {
            throw new HttpException(422, 'Целевой сервер не должен входить в список объединяемых.');
        }
        $targetServer = Server::where('id', $targetServerId)
            ->where('localization_id', $localization->id)
            ->whereNull('merged_into_server_id')
            ->first();
        if (!$targetServer) {
            throw new HttpException(404, 'Целевой сервер не найден или уже объединён.');
        }
        $sourceServers = Server::whereIn('id', $sourceServerIds)
            ->where('localization_id', $localization->id)
            ->whereNull('merged_into_server_id')
            ->get();
        if ($sourceServers->count() !== count($sourceServerIds)) {
            throw new HttpException(422, 'Не все объединяемые сервера найдены или они уже объединены.');
        }
        $sourceIds = $sourceServers->pluck('id')->all();
        Character::whereIn('server_id', $sourceIds)->update(['server_id' => $targetServer->id]);
        Guild::whereIn('server_id', $sourceIds)->update(['server_id' => $targetServer->id]);
        foreach ($sourceServers as $server) {
            $server->update([
                'merged_into_server_id' => $targetServer->id,
                'is_active' => false,
            ]);
        }
        return [
            'message' => 'Сервера объединены.',
            'target_server_id' => $targetServer->id,
        ];
    }
}
