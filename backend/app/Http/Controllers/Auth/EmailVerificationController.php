<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationController extends Controller
{
    private const RESEND_MAX_ATTEMPTS = 3;

    private const RESEND_DECAY_SECONDS = 30 * 60;

    /**
     * Подтверждение email по подписанной ссылке (клик из письма).
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Неверная ссылка подтверждения.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return redirect($frontendUrl.'/login?verified=1');
    }

    /**
     * Повторная отправка письма подтверждения (публичный, без auth).
     * Лимит: 3 отправки, затем пауза 30 минут.
     */
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = mb_strtolower($request->input('email'));
        $rateLimitKey = 'email-verification-resend:'.$email;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RESEND_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Слишком много попыток. Повторите через '.ceil($seconds / 60).' мин.',
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where('email', $email)
            ->whereNull('provider')
            ->whereNull('email_verified_at')
            ->first();

        if ($user) {
            $user->sendEmailVerificationNotification();
            RateLimiter::hit($rateLimitKey, self::RESEND_DECAY_SECONDS);
        }

        return response()->json([
            'message' => 'Если аккаунт существует и email не подтверждён, письмо отправлено.',
        ]);
    }
}
