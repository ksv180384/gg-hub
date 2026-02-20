<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Проверяет, что у пользователя есть право с указанным slug (или роль admin).
     *
     * @param  string  $slug  Slug права, например access.admin
     */
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $permissions = $user->getAllPermissionSlugs();
        if (!in_array($slug, $permissions, true)) {
            return response()->json(['message' => 'Недостаточно прав.'], 403);
        }

        return $next($request);
    }
}
