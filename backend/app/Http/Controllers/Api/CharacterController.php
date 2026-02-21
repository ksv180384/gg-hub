<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Resources\Character\CharacterResource;
use Domains\Character\Actions\CreateCharacterAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterController extends Controller
{
    public function __construct(
        private CharacterRepositoryInterface $characterRepository,
        private CreateCharacterAction $createCharacterAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $characters = $this->characterRepository->getByUserWithContext($request->user()->id);
        return CharacterResource::collection($characters);
    }

    public function store(StoreCharacterRequest $request): JsonResponse
    {
        $character = ($this->createCharacterAction)($request->user(), $request->validated());
        return (new CharacterResource($character))->response()->setStatusCode(201);
    }
}
