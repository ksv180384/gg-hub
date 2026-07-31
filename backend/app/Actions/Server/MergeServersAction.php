<?php

namespace App\Actions\Server;

use Domains\Game\Jobs\ProcessServerMerge;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Game\Models\ServerMerge;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MergeServersAction
{
    /**
     * @param  array<int, int>  $sourceServerIds
     */
    public function __invoke(
        Game $game,
        Localization $localization,
        int $targetServerId,
        array $sourceServerIds,
        ?int $requestedByUserId = null,
    ): ServerMerge {
        if ($localization->game_id !== (int) $game->id) {
            throw new HttpException(404, 'Локализация не принадлежит этой игре.');
        }

        $sourceServerIds = array_map('intval', $sourceServerIds);

        if (count($sourceServerIds) !== count(array_unique($sourceServerIds))) {
            throw new HttpException(422, 'Список объединяемых серверов содержит дубликаты.');
        }

        if (in_array($targetServerId, $sourceServerIds, true)) {
            throw new HttpException(422, 'Целевой сервер не должен входить в список объединяемых.');
        }

        $merge = DB::transaction(function () use (
            $game,
            $localization,
            $targetServerId,
            $sourceServerIds,
            $requestedByUserId,
        ): ServerMerge {
            $serverIds = array_merge([$targetServerId], $sourceServerIds);
            $servers = Server::query()
                ->whereIn('id', $serverIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($servers->count() !== count($serverIds)) {
                throw new HttpException(422, 'Не все серверы найдены.');
            }

            foreach ($servers as $server) {
                if (
                    (int) $server->game_id !== (int) $game->id
                    || (int) $server->localization_id !== (int) $localization->id
                ) {
                    throw new HttpException(422, 'Все серверы должны относиться к выбранной игре и локализации.');
                }

                if ($server->merged_into_server_id !== null) {
                    throw new HttpException(422, 'Один из серверов уже был объединен.');
                }

                if ($server->is_merging) {
                    throw new HttpException(422, 'Один из серверов уже участвует в объединении.');
                }
            }

            $totals = [
                ServerMerge::STAGE_CHARACTERS => DB::table('characters')
                    ->whereIn('server_id', $sourceServerIds)
                    ->count(),
                ServerMerge::STAGE_GUILDS => DB::table('guilds')
                    ->whereIn('server_id', $sourceServerIds)
                    ->count(),
                ServerMerge::STAGE_CONSTANT_PARTIES => DB::table('constant_parties')
                    ->whereIn('server_id', $sourceServerIds)
                    ->count(),
                ServerMerge::STAGE_SERVER_GROUPS => DB::table('server_group_server')
                    ->whereIn('server_id', $sourceServerIds)
                    ->count(),
            ];

            $progress = collect($totals)
                ->map(fn (int $total): array => ['total' => $total, 'processed' => 0])
                ->all();

            Server::query()
                ->whereIn('id', $serverIds)
                ->update(['is_merging' => true]);

            Server::query()
                ->whereIn('id', $sourceServerIds)
                ->update(['is_active' => false]);

            return ServerMerge::query()->create([
                'game_id' => $game->id,
                'localization_id' => $localization->id,
                'target_server_id' => $targetServerId,
                'requested_by_user_id' => $requestedByUserId,
                'source_server_ids' => array_values($sourceServerIds),
                'status' => ServerMerge::STATUS_PENDING,
                'current_stage' => ServerMerge::STAGE_CHARACTERS,
                'total_records' => array_sum($totals),
                'processed_records' => 0,
                'progress' => $progress,
            ]);
        });

        ProcessServerMerge::dispatch($merge->id);

        return $merge->fresh();
    }
}
