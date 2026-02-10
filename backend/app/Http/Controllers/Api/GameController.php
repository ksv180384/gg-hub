<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\GameRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StoreGameRequest;
use App\Http\Resources\Game\GameResource;
use Domains\Game\Actions\CreateGameAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function __construct(
        private GameRepositoryInterface $gameRepository,
        private CreateGameAction $createGameAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $games = $this->gameRepository->getActive();
        return GameResource::collection($games);
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $game = $this->createGameAction->execute($validated + ['is_active' => true]);
        return (new GameResource($game))->response()->setStatusCode(201);
    }
}
