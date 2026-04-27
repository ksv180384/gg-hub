<?php

namespace App\Http\Responses\Fortify;

use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
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
            if (! $user) {
                return response()->json(['user' => null], 201);
            }

            if ($user->isEmailRegistered() && ! $user->hasVerifiedEmail()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'message' => 'На ваш email отправлено письмо для подтверждения регистрации.',
                    'requires_email_verification' => true,
                ], 201);
            }

            $user->load('roles', 'directPermissions');

            return response()->json([
                'user' => (new UserResource($user))->resolve(),
            ], 201);
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
