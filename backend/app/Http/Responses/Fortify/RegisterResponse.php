<?php

namespace App\Http\Responses\Fortify;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
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
                return response()->json(['user' => null], 201);
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
            ], 201);
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
