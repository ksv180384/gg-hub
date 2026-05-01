<?php

namespace Domains\Guild\Actions;

use App\Actions\Notification\SendGuildDiscordNotificationAction;
use App\Services\Notifications\GuildLinkBuilder;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;

class SubmitGuildApplicationAction
{
    public function __construct(
        private SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function __invoke(Guild $guild, int $characterId, array $formData): GuildApplication
    {
        $application = GuildApplication::create([
            'guild_id' => $guild->id,
            'character_id' => $characterId,
            'form_data' => $formData,
            'status' => 'pending',
        ]);

        $characterName = Character::query()->whereKey($characterId)->value('name') ?? 'Персонаж';
        $url = $this->linkBuilder->applicationUrl($guild, (int) $application->id);
        $message = "Новая заявка вступления в гильдию от {$characterName}\n{$url}";
        ($this->sendGuildDiscordNotificationAction)(
            $guild,
            'discord_notify_application_new',
            $message,
        );

        return $application;
    }
}
