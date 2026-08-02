<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConstantPartyChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('features.constant_party_chat'), 404);

        return $next($request);
    }
}
