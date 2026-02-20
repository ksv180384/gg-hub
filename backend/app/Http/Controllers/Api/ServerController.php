<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\MergeServersRequest;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Http\Resources\Game\ServerResource;
use App\Models\Game;
use App\Models\Localization;
use App\Models\Server;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServerController extends Controller
{
    public function index(Game $game, Localization $localization): AnonymousResourceCollection|JsonResponse
    {
        if ($localization->game_id !== (int) $game->id) {
            return response()->json(['message' => 'Локализация не принадлежит этой игре.'], 404);
        }
        $servers = $localization->servers()
            ->whereNull('merged_into_server_id')
            ->orderBy('name')
            ->get();
        return ServerResource::collection($servers);
    }

    public function store(StoreServerRequest $request, Game $game, Localization $localization): JsonResponse
    {
        if ($localization->game_id !== (int) $game->id) {
            return response()->json(['message' => 'Локализация не принадлежит этой игре.'], 404);
        }
        $validated = $request->validated();
        $server = $localization->servers()->create([
            'game_id' => $game->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_active' => $validated['is_active'] ?? true,
        ]);
        return (new ServerResource($server))->response()->setStatusCode(201);
    }

    public function update(UpdateServerRequest $request, Server $server): ServerResource
    {
        $validated = $request->validated();
        $server->update($validated);
        return new ServerResource($server);
    }

    public function destroy(Server $server): Response
    {
        $server->delete();
        return response()->noContent();
    }

    /**
     * Объединить несколько серверов в один: персонажи и гильдии с исходных серверов
     * переносятся на целевой сервер, исходные серверы помечаются как объединённые.
     */
    public function merge(MergeServersRequest $request, Game $game, Localization $localization): JsonResponse
    {
        if ($localization->game_id !== (int) $game->id) {
            return response()->json(['message' => 'Локализация не принадлежит этой игре.'], 404);
        }

        $targetId = (int) $request->input('target_server_id');
        $sourceIds = array_map('intval', (array) $request->input('source_server_ids'));

        if (in_array($targetId, $sourceIds, true)) {
            return response()->json(['message' => 'Целевой сервер не должен входить в список объединяемых.'], 422);
        }

        $targetServer = Server::where('id', $targetId)
            ->where('localization_id', $localization->id)
            ->whereNull('merged_into_server_id')
            ->first();

        if (!$targetServer) {
            return response()->json(['message' => 'Целевой сервер не найден или уже объединён.'], 404);
        }

        $sourceServers = Server::whereIn('id', $sourceIds)
            ->where('localization_id', $localization->id)
            ->whereNull('merged_into_server_id')
            ->get();

        if ($sourceServers->count() !== count($sourceIds)) {
            return response()->json(['message' => 'Не все объединяемые сервера найдены или они уже объединены.'], 422);
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

        return response()->json([
            'message' => 'Сервера объединены.',
            'target_server_id' => $targetServer->id,
        ]);
    }
}
