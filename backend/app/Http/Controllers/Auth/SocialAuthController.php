<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'twitch'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $socialUser = Socialite::driver($provider)->stateless()->user();

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user && $socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())
                ->whereNull('provider')
                ->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $user->avatar ?: $socialUser->getAvatar(),
                ]);
            }
        }

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $socialUser->getEmail(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        }

        if ($user->isBanned()) {
            $frontendUrl = $this->frontendUrl();
            return redirect($frontendUrl . '/login?error=banned');
        }

        Auth::login($user, remember: true);

        return redirect($this->frontendUrl());
    }

    private function frontendUrl(): string
    {
        return rtrim(config('app.frontend_url', config('app.url')), '/');
    }
}
