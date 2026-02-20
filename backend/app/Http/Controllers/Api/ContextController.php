<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContextResource;
use App\Services\SubdomainContext;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function __construct(
        private SubdomainContext $subdomainContext
    ) {}

    /**
     * Контекст текущего запроса: режим (admin / game / main) и при игре — данные игры.
     */
    public function show(Request $request): ContextResource
    {
        $context = $this->subdomainContext->getContext($request);
        return new ContextResource($context);
    }
}
