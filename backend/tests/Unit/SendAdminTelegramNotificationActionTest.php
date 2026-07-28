<?php

use App\Actions\Notification\SendAdminTelegramNotificationAction;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;

it('schedules an admin notification after the response', function () {
    Bus::fake();

    app(SendAdminTelegramNotificationAction::class)(
        'Тестовое системное событие.',
    );

    Bus::assertDispatchedAfterResponse(CallQueuedClosure::class);
});
