<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\StoreGuildRequest;
use App\Http\Requests\Guild\UpdateGuildRequest;
use App\Http\Resources\Guild\GuildResource;
use Domains\Guild\Actions\CreateGuildAction;
use Domains\Guild\Actions\GetGuildAction;
use Domains\Guild\Actions\ListGuildsAction;
use Domains\Guild\Actions\UpdateGuildAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildController extends Controller
{
    public function __construct(
        private ListGuildsAction $listGuildsAction,
        private CreateGuildAction $createGuildAction,
        private GetGuildAction $getGuildAction,
        private UpdateGuildAction $updateGuildAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $guilds = ($this->listGuildsAction)($request);
        return GuildResource::collection($guilds);
    }

    public function show(Guild $guild): JsonResponse
    {
        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader', 'tags']);
        return response()->json(new GuildResource($guild));
    }

    /**
     * Данные гильдии для страницы настроек. Доступно только участникам гильдии.
     * При отсутствии членства возвращается 403 — данные не отдаются.
     */
    public function settings(Guild $guild): JsonResponse
    {
        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader', 'tags']);
        return response()->json(new GuildResource($guild));
    }

    public function store(StoreGuildRequest $request): JsonResponse
    {
        $guild = ($this->createGuildAction)($request->user(), $request->validated());
        return (new GuildResource($guild))->response()->setStatusCode(201);
    }

    public function update(UpdateGuildRequest $request, Guild $guild): JsonResponse
    {
        $guild = ($this->updateGuildAction)($guild, $request);
        return response()->json(new GuildResource($guild));
    }
}
