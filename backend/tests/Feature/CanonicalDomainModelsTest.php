<?php

use Domains\Analytics\Models\LandingCtaClick;
use Domains\Notification\Models\Notification;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the domain user model for auth factories and notifications', function () {
    $user = User::factory()->create();

    $notification = $user->notifications()->create([
        'message' => 'Test notification',
        'link' => '/test',
    ]);

    expect(config('auth.providers.users.model'))->toBe(User::class)
        ->and($user)->toBeInstanceOf(User::class)
        ->and($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->user)->toBeInstanceOf(User::class);
});

it('stores landing analytics with the domain model', function () {
    $click = LandingCtaClick::query()->create([
        'button' => 'register',
        'user_agent' => 'Pest',
        'ip_address' => '127.0.0.1',
    ]);

    expect($click)->toBeInstanceOf(LandingCtaClick::class);
});
