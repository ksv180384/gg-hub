<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $appName = Config::get('app.name', 'GG-hub');
        $frontendUrl = rtrim(
            (string) (Config::get('app.frontend_url') ?: Config::get('app.url')),
            '/',
        );
        $expiresInMinutes = (int) Config::get(
            'auth.passwords.'.Config::get('auth.defaults.passwords').'.expire',
            60,
        );
        $userName = trim((string) $notifiable->name) ?: 'игрок';

        $viewData = [
            'appName' => $appName,
            'expiresInMinutes' => $expiresInMinutes,
            'frontendUrl' => $frontendUrl,
            'logoSource' => $this->logoSource($frontendUrl),
            'resetUrl' => $this->resetUrl($notifiable),
            'userName' => $userName,
        ];

        return (new MailMessage)
            ->subject('Восстановление пароля — '.$appName)
            ->view('emails.auth.reset-password', $viewData)
            ->text('emails.auth.reset-password-text', $viewData);
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
