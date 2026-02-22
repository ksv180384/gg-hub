<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanUpdateUserRolesPermissions
{
    private const PERMISSION_ROLES = 'izmeniat-rol-polzovatelia';
    private const PERMISSION_PERMISSIONS = 'izmeniat-prava-polzovatelia';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $data = $request->input();
        $slugs = $user->getAllPermissionSlugs();

        if (array_key_exists('role_ids', $data) && !in_array(self::PERMISSION_ROLES, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для изменения роли пользователя.'], 403);
        }
        if (array_key_exists('permission_ids', $data) && !in_array(self::PERMISSION_PERMISSIONS, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для изменения прав пользователя.'], 403);
        }

        return $next($request);
    }
}
