<?php

namespace App\Http\Middleware;

use App\Services\SubdomainContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSubdomain
{
    public function __construct(
        private SubdomainContext $subdomainContext
    ) {}

    /**
     * Допускает запрос только с субдомена admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->subdomainContext->isAdmin($request)) {
            return response()->json(['message' => 'Доступ только с админского субдомена.'], 403);
        }

        return $next($request);
    }
}
