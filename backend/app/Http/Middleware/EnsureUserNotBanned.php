<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBanned
{
    /**
     * Запрещает доступ заблокированным пользователям к определённым действиям.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        if ($user->isBanned()) {
            return response()->json([
                'message' => 'Ваш аккаунт заблокирован. Вы не можете выполнять это действие.',
            ], 403);
        }

        return $next($request);
    }
}
