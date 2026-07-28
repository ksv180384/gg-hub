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
        $appName = Config::get('app.name', 'GG-hub');
        $frontendUrl = rtrim(
            (string) (Config::get('app.frontend_url') ?: Config::get('app.url')),
            '/',
        );
        $userName = trim((string) $notifiable->name) ?: 'игрок';
        $verificationUrl = $this->verificationUrl($notifiable);

        $viewData = [
            'appName' => $appName,
            'frontendUrl' => $frontendUrl,
            'logoSource' => $this->logoSource($frontendUrl),
            'userName' => $userName,
            'verificationUrl' => $verificationUrl,
        ];

        return (new MailMessage)
            ->subject('Подтверждение email — '.$appName)
            ->view('emails.auth.verify-email', $viewData)
            ->text('emails.auth.verify-email-text', $viewData);
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

    protected function logoSource(string $frontendUrl): string
    {
        $configuredLogo = Config::get('mail.logo');

        if (is_string($configuredLogo) && $configuredLogo !== '') {
            return $configuredLogo;
        }

        $logoPath = Config::get(
            'mail.logo_path',
            public_path('images/mail/gg-hub-logo.png'),
        );

        if (is_string($logoPath) && is_file($logoPath)) {
            return 'data:image/png;base64,'.base64_encode(
                (string) file_get_contents($logoPath),
            );
        }

        return $frontendUrl.'/images/mail/gg-hub-logo.png';
    }
}
