<?php

namespace App\Http\Controllers\Api;

use App\Actions\GameClass\CreateGameClassAction;
use App\Actions\GameClass\DeleteGameClassAction;
use App\Actions\GameClass\UpdateGameClassAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\GameClass\StoreGameClassRequest;
use App\Http\Requests\GameClass\UpdateGameClassRequest;
use App\Http\Resources\Game\GameClassResource;
use App\Models\Game;
use App\Models\GameClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GameClassController extends Controller
{
    public function __construct(
        private CreateGameClassAction $createGameClassAction,
        private UpdateGameClassAction $updateGameClassAction,
        private DeleteGameClassAction $deleteGameClassAction
    ) {}

    public function index(Game $game): AnonymousResourceCollection
    {
        $classes = $game->gameClasses()->orderBy('name')->get();
        return GameClassResource::collection($classes);
    }

    public function store(StoreGameClassRequest $request, Game $game): JsonResponse
    {
        $data = $request->validated();
        $gameClass = ($this->createGameClassAction)($game, $data, $request->file('image'));
        return (new GameClassResource($gameClass))->response()->setStatusCode(201);
    }

    public function update(UpdateGameClassRequest $request, GameClass $game_class): GameClassResource
    {
        $data = $request->validated();
        $gameClass = ($this->updateGameClassAction)(
            $game_class,
            $data,
            $request->file('image'),
            $request->boolean('remove_image')
        );
        return new GameClassResource($gameClass);
    }

    public function destroy(GameClass $game_class): Response
    {
        ($this->deleteGameClassAction)($game_class);
        return response()->noContent();
    }
}
