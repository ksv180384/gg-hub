<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Notifications\VerifyEmailNotification;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('resends verification email for an unverified email user', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['provider' => null]);

    $response = app(AdminUserController::class)->resendVerification($user);

    expect($response->getStatusCode())->toBe(200);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('does not resend verification email when verification is unavailable', function (array $attributes) {
    Notification::fake();
    $user = User::factory()->create($attributes);

    expect(fn () => app(AdminUserController::class)->resendVerification($user))
        ->toThrow(HttpException::class);

    Notification::assertNothingSent();
})->with([
    'verified email user' => [['email_verified_at' => now(), 'provider' => null]],
    'social user' => [['email_verified_at' => null, 'provider' => 'yandex']],
]);
