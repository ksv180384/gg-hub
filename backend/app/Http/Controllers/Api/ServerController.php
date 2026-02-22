<?php

namespace App\Http\Controllers\Api;

use App\Actions\Server\CreateServerAction;
use App\Actions\Server\DeleteServerAction;
use App\Actions\Server\ListServersAction;
use App\Actions\Server\MergeServersAction;
use App\Actions\Server\UpdateServerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\MergeServersRequest;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Http\Resources\Game\ServerResource;
use App\Models\Game;
use App\Models\Localization;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServerController extends Controller
{
    public function __construct(
        private ListServersAction $listServersAction,
        private CreateServerAction $createServerAction,
        private UpdateServerAction $updateServerAction,
        private DeleteServerAction $deleteServerAction,
        private MergeServersAction $mergeServersAction
    ) {}

    public function index(Game $game, Localization $localization): AnonymousResourceCollection|JsonResponse
    {
        $servers = ($this->listServersAction)($game, $localization);
        return ServerResource::collection($servers);
    }

    public function store(StoreServerRequest $request, Game $game, Localization $localization): JsonResponse
    {
        $server = ($this->createServerAction)($game, $localization, $request->validated());
        return (new ServerResource($server))->response()->setStatusCode(201);
    }

    public function update(UpdateServerRequest $request, Server $server): ServerResource
    {
        $server = ($this->updateServerAction)($server, $request->validated());
        return new ServerResource($server);
    }

    public function destroy(Server $server): Response
    {
        ($this->deleteServerAction)($server);
        return response()->noContent();
    }

    /**
     * Объединить несколько серверов в один: персонажи и гильдии с исходных серверов
     * переносятся на целевой сервер, исходные серверы помечаются как объединённые.
     */
    public function merge(MergeServersRequest $request, Game $game, Localization $localization): JsonResponse
    {
        $targetId = (int) $request->input('target_server_id');
        $sourceIds = array_map('intval', (array) $request->input('source_server_ids'));
        $result = ($this->mergeServersAction)($game, $localization, $targetId, $sourceIds);
        return response()->json($result);
    }
}
