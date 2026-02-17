<?php

namespace App\Http\Responses\Fortify;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            $user = $request->user();
            if (!$user) {
                return response()->json(['user' => null, 'two_factor' => false]);
            }
            $user->load('roles', 'directPermissions');
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permissions' => $user->getAllPermissionSlugs(),
                    'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
                ],
                'two_factor' => false,
            ]);
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
