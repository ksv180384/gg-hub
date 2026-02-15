<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Game\GameResource;
use App\Services\SubdomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function __construct(
        private SubdomainContext $subdomainContext
    ) {}

    /**
     * Контекст текущего запроса: режим (admin / game / main) и при игре — данные игры.
     */
    public function show(Request $request): JsonResponse
    {
        $context = $this->subdomainContext->getContext($request);

        $data = [
            'mode' => $context['mode'],
            'subdomain' => $context['subdomain'],
            'game' => $context['game'] ? new GameResource($context['game']) : null,
        ];

        return response()->json(['data' => $data]);
    }
}
