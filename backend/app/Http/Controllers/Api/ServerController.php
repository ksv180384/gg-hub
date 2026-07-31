<?php

namespace App\Http\Controllers\Api;

use App\Actions\Server\CreateServerAction;
use App\Actions\Server\DeleteServerAction;
use App\Actions\Server\ListServersAction;
use App\Actions\Server\MergeServersAction;
use App\Actions\Server\ResumeServerMergeAction;
use App\Actions\Server\UpdateServerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\MergeServersRequest;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Http\Resources\Game\ServerMergeResource;
use App\Http\Resources\Game\ServerResource;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Game\Models\ServerMerge;
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
        private MergeServersAction $mergeServersAction,
        private ResumeServerMergeAction $resumeServerMergeAction,
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

    public function merge(MergeServersRequest $request, Game $game, Localization $localization): JsonResponse
    {
        $merge = ($this->mergeServersAction)(
            $game,
            $localization,
            (int) $request->input('target_server_id'),
            array_map('intval', (array) $request->input('source_server_ids')),
            $request->user()?->id,
        );

        return (new ServerMergeResource($merge))
            ->response()
            ->setStatusCode(202);
    }

    public function currentMerge(Game $game, Localization $localization): JsonResponse|ServerMergeResource
    {
        if ((int) $localization->game_id !== (int) $game->id) {
            abort(404);
        }

        $merge = ServerMerge::query()
            ->where('game_id', $game->id)
            ->where('localization_id', $localization->id)
            ->latest('id')
            ->first();

        return $merge
            ? new ServerMergeResource($merge)
            : response()->json(['data' => null]);
    }

    public function showMerge(ServerMerge $serverMerge): ServerMergeResource
    {
        return new ServerMergeResource($serverMerge);
    }

    public function resumeMerge(ServerMerge $serverMerge): ServerMergeResource
    {
        return new ServerMergeResource(
            ($this->resumeServerMergeAction)($serverMerge),
        );
    }
}
