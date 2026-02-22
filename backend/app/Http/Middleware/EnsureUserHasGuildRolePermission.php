<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasGuildRolePermission
{
    /**
     * Проверяет, что у пользователя в этой гильдии есть хотя бы одно из указанных прав.
     * Должен использоваться после guild.member.
     *
     * @param  string  $allowedSlugs  Список slug через запятую, например: dobavliat-rol,udaliat-rol
     */
    public function handle(Request $request, Closure $next, string $allowedSlugs): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $guild = $request->route('guild');
        if (!$guild instanceof Guild) {
            return $next($request);
        }

        $required = array_map('trim', explode(',', $allowedSlugs));
        $required = array_filter($required);

        $userSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);

        $hasAny = $userSlugs->contains(fn (string $slug): bool => in_array($slug, $required, true));

        if (!$hasAny) {
            return response()->json([
                'message' => 'Недостаточно прав в гильдии для этого действия.',
            ], 403);
        }

        return $next($request);
    }

    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}
}
