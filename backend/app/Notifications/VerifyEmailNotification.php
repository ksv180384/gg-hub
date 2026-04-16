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

        return (new MailMessage())
            ->subject('Подтверждение email — '.Config::get('app.name'))
            ->greeting('Здравствуйте, '.$notifiable->name.'!')
            ->line('Благодарим за регистрацию. Для завершения создания аккаунта подтвердите ваш email-адрес, нажав на кнопку ниже.')
            ->action('Подтвердить email', $verificationUrl)
            ->line('Ссылка действительна в течение 60 минут.')
            ->line('Если вы не создавали аккаунт, просто проигнорируйте это письмо.')
            ->salutation('С уважением, команда '.Config::get('app.name'));
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
