<?php

use Domains\Access\Models\Role;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

it('assigns the default role to a newly registered social user', function () {
    $role = Role::query()->create([
        'name' => 'Пользователь',
        'slug' => 'polzovatel',
        'description' => 'Роль по умолчанию для зарегистрированных пользователей',
    ]);
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('12345');
    $socialUser->shouldReceive('getName')->andReturn('Social User');
    $socialUser->shouldReceive('getEmail')->andReturn('social@example.com');
    $socialUser->shouldReceive('getAvatar')->andReturnNull();

    $driver = Mockery::mock();
    $driver->shouldReceive('stateless')->once()->andReturnSelf();
    $driver->shouldReceive('user')->once()->andReturn($socialUser);
    Socialite::shouldReceive('driver')->once()->with('yandex')->andReturn($driver);

    $this->get('/auth/yandex/callback?code=authorization-code')
        ->assertRedirect(config('app.frontend_url'));

    $user = User::query()->where('email', 'social@example.com')->firstOrFail();

    expect($user->roles()->pluck('id')->all())->toBe([$role->id]);
});
