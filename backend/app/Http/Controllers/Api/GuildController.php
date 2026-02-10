<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\StoreGuildRequest;
use App\Http\Resources\Guild\GuildResource;
use Domains\Guild\Actions\CreateGuildAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildController extends Controller
{
    public function __construct(
        private GuildRepositoryInterface $guildRepository,
        private CreateGuildAction $createGuildAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 15);
        $filters = array_filter([
            'game_id' => $request->input('game_id') ? (int) $request->input('game_id') : null,
            'localization_id' => $request->input('localization_id') ? (int) $request->input('localization_id') : null,
            'server_id' => $request->input('server_id') ? (int) $request->input('server_id') : null,
        ]);
        $guilds = $this->guildRepository->getPaginatedWithContext($perPage, $filters);
        return GuildResource::collection($guilds);
    }

    public function store(StoreGuildRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['owner_id'] = $request->user()->id;
        $guild = $this->createGuildAction->execute($validated);
        $guild->load(['game', 'localization', 'server']);
        return (new GuildResource($guild))->response()->setStatusCode(201);
    }
}
