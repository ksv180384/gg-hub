<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\GameRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StoreGameRequest;
use App\Http\Requests\Game\UpdateGameRequest;
use App\Http\Resources\Game\GameResource;
use App\Actions\Game\CreateGameAction;
use App\Models\Game;
use App\Services\GameImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function __construct(
        private GameRepositoryInterface $gameRepository,
        private CreateGameAction $createGameAction,
        private GameImageService $gameImageService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $games = $this->gameRepository->getActive()->load('localizations');
        return GameResource::collection($games);
    }

    public function show(Game $game): GameResource
    {
        $game->load(['localizations' => fn ($q) => $q->with(['servers' => fn ($q) => $q->whereNull('merged_into_server_id')->orderBy('name')])]);
        return new GameResource($game);
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['image']);
        $game = $this->createGameAction->execute($validated + ['is_active' => true]);
        if ($request->hasFile('image')) {
            $path = $this->gameImageService->storeWithVariants($request->file('image'), $game->id);
            $game->update(['image' => $path]);
        }
        $game->load('localizations');
        return (new GameResource($game))->response()->setStatusCode(201);
    }

    public function update(UpdateGameRequest $request, Game $game): GameResource
    {
        $validated = $request->validated();
        unset($validated['image'], $validated['remove_image']);

        $game->update($validated);

        if ($request->boolean('remove_image')) {
            $game->deleteImageFiles();
            $game->update(['image' => null]);
        } elseif ($request->hasFile('image')) {
            $game->deleteImageFiles();
            $path = $this->gameImageService->storeWithVariants($request->file('image'), $game->id);
            $game->update(['image' => $path]);
        }

        $game->load('localizations');
        return new GameResource($game);
    }

    public function destroy(Game $game): Response
    {
        $game->delete();
        return response()->noContent();
    }
}
