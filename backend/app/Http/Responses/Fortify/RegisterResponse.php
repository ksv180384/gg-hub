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
            return response()->json([
                'user' => $user ? $user->only(['id', 'name', 'email']) : null,
            ], 201);
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
