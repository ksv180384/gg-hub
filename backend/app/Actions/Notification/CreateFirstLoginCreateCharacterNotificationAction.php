<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;

/**
 * Создаёт оповещение пользователю при первой авторизации:
 * предложение создать персонажа со ссылкой на страницу создания.
 */
class CreateFirstLoginCreateCharacterNotificationAction
{
    public function __invoke(User $user): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'message' => 'Добро пожаловать! Создайте персонажа, чтобы начать пользоваться сервисом.',
            'link' => '/my-characters/create',
        ]);
    }
}

