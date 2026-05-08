<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use Domains\Character\Models\Character;

/**
 * Создаёт оповещение пользователю при создании первого персонажа:
 * предложение создать или вступить в гильдию со ссылкой на каталог гильдий
 * с фильтрами по локализации/серверу персонажа и только с открытым набором.
 */
class CreateFirstCharacterGuildSuggestionNotificationAction
{
    public function __invoke(Character $character): Notification
    {
        $gameId = (int) $character->game_id;
        $localizationId = (int) $character->localization_id;
        $serverId = (int) $character->server_id;

        $query = http_build_query([
            'game_id' => $gameId,
            'localization_ids' => (string) $localizationId,
            'server_ids' => (string) $serverId,
            'is_recruiting' => '1',
        ]);

        return Notification::create([
            'user_id' => (int) $character->user_id,
            'message' => 'Персонаж создан! Хотите создать гильдию или вступить в существующую? В каталоге уже включены фильтры по вашему серверу и открытому набору.',
            'link' => '/guilds' . ($query !== '' ? ('?' . $query) : ''),
        ]);
    }
}

