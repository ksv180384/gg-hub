<?php

use Domains\Notification\Models\Notification;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('deletes notifications that have been stored for three months', function () {
    Carbon::setTestNow('2026-08-02 12:00:00');

    $user = User::factory()->create();

    $expiredUnread = Notification::query()->forceCreate([
        'user_id' => $user->id,
        'message' => 'Expired unread notification',
        'created_at' => now()->subMonthsNoOverflow(3),
        'updated_at' => now()->subMonthsNoOverflow(3),
    ]);
    $expiredRead = Notification::query()->forceCreate([
        'user_id' => $user->id,
        'message' => 'Expired read notification',
        'read_at' => now()->subMonth(),
        'created_at' => now()->subMonthsNoOverflow(3)->subSecond(),
        'updated_at' => now()->subMonthsNoOverflow(3)->subSecond(),
    ]);
    $active = Notification::query()->forceCreate([
        'user_id' => $user->id,
        'message' => 'Active notification',
        'created_at' => now()->subMonthsNoOverflow(3)->addSecond(),
        'updated_at' => now()->subMonthsNoOverflow(3)->addSecond(),
    ]);

    Artisan::call('notifications:prune-expired');

    expect(Notification::query()->find($expiredUnread->id))->toBeNull()
        ->and(Notification::query()->find($expiredRead->id))->toBeNull()
        ->and(Notification::query()->find($active->id))->not->toBeNull()
        ->and(Artisan::output())->toContain('Deleted 2 expired notification(s).');
});
