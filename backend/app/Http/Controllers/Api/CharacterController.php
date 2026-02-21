<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Http\Resources\Character\CharacterResource;
use Domains\Character\Actions\CreateCharacterAction;
use Domains\Character\Actions\DeleteCharacterAction;
use Domains\Character\Actions\UpdateCharacterAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CharacterController extends Controller
{
    public function __construct(
        private CharacterRepositoryInterface $characterRepository,
        private CreateCharacterAction $createCharacterAction,
        private UpdateCharacterAction $updateCharacterAction,
        private DeleteCharacterAction $deleteCharacterAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;
        $gameId = $request->query('game_id') ? (int) $request->query('game_id') : null;
        $serverId = $request->query('server_id') ? (int) $request->query('server_id') : null;
        $availableForGuildLeader = $request->boolean('available_for_guild_leader');

        if ($availableForGuildLeader && $gameId !== null && $serverId !== null) {
            $characters = $this->characterRepository->getByUserAvailableForGuildLeader($userId, $gameId, $serverId);
        } else {
            $characters = $this->characterRepository->getByUserWithContext($userId, $gameId);
        }

        return CharacterResource::collection($characters);
    }

    public function show(Request $request, int $character): JsonResponse|CharacterResource
    {
        $model = $this->characterRepository->findByIdAndUser($character, $request->user()->id);
        if ($model === null) {
            throw new NotFoundHttpException('Персонаж не найден.');
        }
        return new CharacterResource($model);
    }

    public function store(StoreCharacterRequest $request): JsonResponse
    {
        $character = ($this->createCharacterAction)(
            $request->user(),
            $request->validated(),
            $request->file('avatar')
        );
        return (new CharacterResource($character))->response()->setStatusCode(201);
    }

    public function update(UpdateCharacterRequest $request, int $character): CharacterResource
    {
        $model = $this->characterRepository->findByIdAndUser($character, $request->user()->id);
        if ($model === null) {
            throw new NotFoundHttpException('Персонаж не найден.');
        }
        $validated = $request->validated();
        $updated = ($this->updateCharacterAction)(
            $model,
            $validated,
            $request->file('avatar'),
            $request->boolean('remove_avatar')
        );
        return new CharacterResource($updated);
    }

    public function destroy(Request $request, int $character): Response
    {
        $model = $this->characterRepository->findByIdAndUser($character, $request->user()->id);
        if ($model === null) {
            throw new NotFoundHttpException('Персонаж не найден.');
        }
        ($this->deleteCharacterAction)($model);
        return response()->noContent();
    }
}
