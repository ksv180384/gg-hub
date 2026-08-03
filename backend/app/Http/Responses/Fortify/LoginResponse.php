<?php

namespace App\Http\Responses\Fortify;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            $user = $request->user();
            if (! $user) {
                return response()->json(['user' => null, 'two_factor' => false]);
            }
            $user->load('roles', 'directPermissions');

            return response()->json([
                'user' => (new UserResource($user))->resolve(),
                'two_factor' => false,
            ]);
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
