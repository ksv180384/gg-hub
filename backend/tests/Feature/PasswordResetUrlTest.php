<?php

use Domains\User\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('renders a russian password reset email with the auth design', function () {
    config([
        'app.name' => 'GG-hub',
        'app.frontend_url' => 'https://gg-hub.ru/',
        'app.url' => 'https://api.gg-hub.ru',
    ]);

    $user = User::factory()->create([
        'name' => 'Игрок',
        'email' => 'player+guild@example.com',
    ]);

    $mailMessage = (new ResetPasswordNotification('reset token'))->toMail($user);
    $html = (string) $mailMessage->render();

    expect($mailMessage->subject)
        ->toBe('Восстановление пароля — GG-hub')
        ->and($mailMessage->viewData['resetUrl'])
        ->toBe(
            'https://gg-hub.ru/reset-password'
            .'?token=reset%20token'
            .'&email=player%2Bguild%40example.com',
        )
        ->and($html)
        ->toContain('Здравствуйте, Игрок!')
        ->toContain('Восстановление пароля')
        ->toContain('Сбросить пароль')
        ->toContain('Ссылка действительна 60 минут')
        ->toContain('Если кнопка «Сбросить пароль» не открывается')
        ->toContain('border: 1px solid #b97410')
        ->toContain('border: 1px solid #9a5e0d')
        ->toContain('border-top: 1px solid #b97717; border-left: 1px solid #b97717')
        ->not->toContain('Reset Password')
        ->not->toContain('If you did not request')
        ->not->toContain('Laravel');
});

it('sends the custom password reset notification from the user model', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->sendPasswordResetNotification('test-token');

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        fn (ResetPasswordNotification $notification): bool => $notification->token === 'test-token',
    );
});
