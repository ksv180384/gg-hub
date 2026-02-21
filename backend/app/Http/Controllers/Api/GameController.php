<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StoreGameRequest;
use App\Http\Requests\Game\UpdateGameRequest;
use App\Http\Resources\Game\GameResource;
use App\Actions\Game\CreateGameAction;
use App\Actions\Game\DeleteGameAction;
use App\Actions\Game\GetGameAction;
use App\Actions\Game\ListGamesAction;
use App\Actions\Game\UpdateGameAction;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function __construct(
        private ListGamesAction $listGamesAction,
        private GetGameAction $getGameAction,
        private CreateGameAction $createGameAction,
        private UpdateGameAction $updateGameAction,
        private DeleteGameAction $deleteGameAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $games = ($this->listGamesAction)();
        return GameResource::collection($games);
    }

    public function show(Game $game): GameResource
    {
        $game = ($this->getGameAction)($game);
        return new GameResource($game);
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $game = ($this->createGameAction)($validated + ['is_active' => true], $request->file('image'));
        return (new GameResource($game))->response()->setStatusCode(201);
    }

    public function update(UpdateGameRequest $request, Game $game): GameResource
    {
        $validated = $request->validated();
        $game = ($this->updateGameAction)(
            $game,
            $validated,
            $request->file('image'),
            $request->boolean('remove_image')
        );
        return new GameResource($game);
    }

    public function destroy(Game $game): Response
    {
        ($this->deleteGameAction)($game);
        return response()->noContent();
    }
}
