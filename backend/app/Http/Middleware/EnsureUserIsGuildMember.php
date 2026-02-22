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
     * Если не состоит — возвращает 403, данные гильдии не отдаются.
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
                'message' => 'Вы не состоите в этой гильдии. Доступ к настройкам запрещён.',
            ], 403);
        }

        return $next($request);
    }
}
