<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Filters\CharacterFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\CharacterFilterRequest;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Http\Resources\Character\CharacterResource;
use App\Models\Game;
use Domains\Character\Models\Character;
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

    /**
     * Все персонажи игры (аватар, имя, классы, локализация, сервер) с поддержкой фильтра.
     */
    public function indexForGame(Game $game, CharacterFilterRequest $request): AnonymousResourceCollection
    {
        $filter = new CharacterFilter($request);
        $characters = Character::query()
            ->where('game_id', $game->id)
            ->with([
                'localization',
                'server',
                'gameClasses',
                'tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'user',
            ])
            ->filter($filter)
            ->orderBy('name')
            ->get();

        return CharacterResource::collection($characters);
    }

    /**
     * Один персонаж игры (страница персонажа).
     */
    public function showForGame(Game $game, int $character): JsonResponse|CharacterResource
    {
        $model = $this->characterRepository->findByIdAndGame($character, $game->id);
        if ($model === null) {
            throw new NotFoundHttpException('Персонаж не найден.');
        }
        return response()->json(new CharacterResource($model));
    }

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
