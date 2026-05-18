<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $appName = Config::get('app.name', 'gg-hub');

        return (new MailMessage())
            ->subject('Подтверждение email — '.$appName)
            ->greeting('Здравствуйте, '.$notifiable->name.'!')
            ->line('Спасибо за регистрацию на '.$appName.'. Чтобы завершить создание аккаунта, подтвердите email — нажмите кнопку ниже.')
            ->action('Подтвердить email', $verificationUrl)
            ->line('Ссылка действительна 60 минут.')
            ->line('Если вы не регистрировались на сайте, просто проигнорируйте это письмо.')
            ->salutation('С уважением, команда '.$appName);
    }

    protected function verificationUrl(mixed $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
