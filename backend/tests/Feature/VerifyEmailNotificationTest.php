<?php

use App\Notifications\VerifyEmailNotification;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the verification email with the gg-hub auth design', function () {
    config([
        'app.name' => 'GG-hub',
        'app.url' => 'https://gg-hub.ru',
        'app.frontend_url' => 'https://gg-hub.ru',
        'app.locale' => 'ru',
    ]);

    $user = User::factory()->create([
        'name' => 'test77',
        'email' => 'test77@example.com',
    ]);

    $html = (string) (new VerifyEmailNotification)->toMail($user)->render();

    expect($html)
        ->toContain('https://gg-hub.ru/images/mail/gg-hub-logo.png')
        ->toContain('Здравствуйте, test77!')
        ->toContain('Спасибо за регистрацию на GG-hub')
        ->toContain('Подтвердите email')
        ->toContain('Твоя гильдия. Твоя команда.')
        ->toContain('background-color: #03090d')
        ->toContain('background-color: #f7ba2b')
        ->toContain('border: 1px solid #b97410')
        ->toContain('border: 1px solid #9a5e0d')
        ->toContain('border-top: 1px solid #b97717; border-left: 1px solid #b97717')
        ->toContain('border-bottom: 1px solid #b97717; border-left: 1px solid #b97717')
        ->toContain('команда GG-hub')
        ->not->toContain('Laravel')
        ->toContain('Если кнопка «Подтвердить email» не открывается')
        ->not->toContain('<br>')
        ->not->toContain('&lt;br&gt;')
        ->not->toContain('laravel.com')
        ->not->toContain('Laravel Logo');
});

it('uses custom logo url from mail config when set', function () {
    config([
        'app.url' => 'https://gg-hub.ru',
        'mail.logo' => 'https://cdn.example.test/logo.png',
    ]);

    $user = User::factory()->create();

    $html = (string) (new VerifyEmailNotification)->toMail($user)->render();

    expect($html)->toContain('https://cdn.example.test/logo.png');
});
