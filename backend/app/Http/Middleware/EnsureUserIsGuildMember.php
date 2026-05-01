<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Domains\Guild\Models\Guild;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsGuildMember
{
    /**
     * Проверяет, что пользователь состоит в гильдии (хотя бы один его персонаж в гильдии).
     * Если не состоит — 404 (ресурс недоступен), без признаков существования закрытого содержимого.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $guild = $request->route('guild');
        if (!$guild instanceof Guild) {
            return $next($request);
        }

        $isMember = $guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'Страница не найдена.',
            ], 404);
        }

        return $next($request);
    }
}
