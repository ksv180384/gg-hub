<?php

namespace App\Actions\Notification;

use Domains\Notification\Models\Notification;
use Domains\Raid\Models\RaidApplication;

class CreateRaidApplicationNotificationAction
{
    public function submitted(RaidApplication $application): ?Notification
    {
        $application->loadMissing(['raid.leader', 'character']);
        $leaderUserId = $application->raid->leader?->user_id;
        if (! $leaderUserId) {
            return null;
        }

        return Notification::query()->create([
            'user_id' => $leaderUserId,
            'message' => "{$application->character->name} подал(а) заявку в рейд «{$application->raid->name}».",
            'link' => "/guilds/{$application->raid->guild_id}/raids",
        ]);
    }

    public function decided(RaidApplication $application): ?Notification
    {
        $application->loadMissing(['raid', 'character', 'decidedBy:id,name']);
        $userId = $application->character?->user_id;
        if (! $userId) {
            return null;
        }

        $deciderName = $application->decidedBy?->name ?? 'Неизвестный пользователь';
        $message = $application->status === RaidApplication::STATUS_ACCEPTED
            ? "Заявка персонажа {$application->character->name} в рейд «{$application->raid->name}» принята. Принял(а): {$deciderName}."
            : "Заявка персонажа {$application->character->name} в рейд «{$application->raid->name}» отклонена. Отклонил(а): {$deciderName}.";

        return Notification::query()->create([
            'user_id' => $userId,
            'message' => $message,
            'link' => "/guilds/{$application->raid->guild_id}/raids",
        ]);
    }
}
