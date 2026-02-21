<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Localization\StoreLocalizationRequest;
use App\Http\Resources\Game\LocalizationResource;
use App\Actions\Game\CreateLocalizationAction;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class LocalizationController extends Controller
{
    public function __construct(
        private CreateLocalizationAction $createLocalizationAction
    ) {}

    public function store(StoreLocalizationRequest $request, Game $game): JsonResponse
    {
        $validated = $request->validated();
        $localization = ($this->createLocalizationAction)($game, $validated);
        return (new LocalizationResource($localization))->response()->setStatusCode(201);
    }
}
