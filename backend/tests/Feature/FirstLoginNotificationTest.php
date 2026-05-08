<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('creates a notification on first login when user has no characters', function () {
    $user = User::factory()->create([
        'first_login_at' => null,
    ]);

    expect($user->characters()->exists())->toBeFalse();
    expect($user->first_login_at)->toBeNull();

    Auth::login($user);

    $user->refresh();
    expect($user->first_login_at)->not->toBeNull();

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);
    $notification = Notification::query()->where('user_id', $user->id)->firstOrFail();
    expect($notification->link)->toBe('/my-characters/create');

    Auth::logout();
    Auth::login($user);

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);
});

