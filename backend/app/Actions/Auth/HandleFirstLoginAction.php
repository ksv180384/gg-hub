<?php

namespace App\Actions\Auth;

use App\Actions\Notification\CreateFirstLoginCreateCharacterNotificationAction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class HandleFirstLoginAction
{
    public function __construct(
        private CreateFirstLoginCreateCharacterNotificationAction $createFirstLoginCreateCharacterNotificationAction,
    ) {}

    public function __invoke(Authenticatable $authenticatable): void
    {
        if (! $authenticatable instanceof User) {
            return;
        }

        $updated = User::query()
            ->whereKey($authenticatable->id)
            ->whereNull('first_login_at')
            ->update(['first_login_at' => now()]);

        if ($updated !== 1) {
            return;
        }

        if ($authenticatable->characters()->exists()) {
            return;
        }

        ($this->createFirstLoginCreateCharacterNotificationAction)($authenticatable);
    }
}

