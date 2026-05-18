<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders verify email with gg-hub branding and without laravel assets', function () {
    config([
        'app.name' => 'gg-hub',
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
        ->toContain('data:image/png;base64,')
        ->toContain('Здравствуйте, test77!')
        ->toContain('Спасибо за регистрацию на gg-hub')
        ->toContain('команда gg-hub')
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
