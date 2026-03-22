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
     * Поддерживает несколько slug через запятую (OR): permission:a,b — нужен любой из них.
     *
     * @param  string  $slug  Slug права, например access.admin или admnistrirovanie,prosmatirivat-golosovaniia
     */
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $permissions = $user->getAllPermissionSlugs();
        $requiredSlugs = array_map('trim', explode(',', $slug));
        $hasAny = false;
        foreach ($requiredSlugs as $s) {
            if (in_array($s, $permissions, true)) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            return response()->json(['message' => 'Недостаточно прав.'], 403);
        }

        return $next($request);
    }
}
