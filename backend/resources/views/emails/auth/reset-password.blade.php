<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>Восстановление пароля — {{ $appName }}</title>
    <style>
        @media only screen and (max-width: 680px) {
            .email-shell {
                width: 100% !important;
            }

            .email-padding {
                padding-right: 16px !important;
                padding-left: 16px !important;
            }

            .email-card-content {
                padding: 34px 24px 30px !important;
            }

            .email-title {
                font-size: 27px !important;
            }
        }
    </style>
</head>
<body style="width: 100%; margin: 0; padding: 0; background-color: #03090d; color: #f6f2e9; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">
        Восстановите доступ к аккаунту {{ $appName }}.
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; background-color: #03090d;">
        <tr>
            <td class="email-padding" align="center" style="padding: 34px 24px 42px;">
                <table class="email-shell" width="620" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 620px; max-width: 620px;">
                    <tr>
                        <td align="center" style="padding: 0 0 24px;">
                            <a href="{{ $frontendUrl }}" style="display: inline-block; color: #f6f2e9; text-decoration: none;">
                                <img src="{{ $logoSource }}" width="64" height="64" alt="{{ $appName }}" style="display: block; width: 64px; height: 64px; margin: 0 auto; border: 0;">
                            </a>
                            <p style="margin: 10px 0 0; color: #dca52b; font-size: 11px; font-weight: 700; letter-spacing: 3px; line-height: 1.4; text-transform: uppercase;">
                                Сообщество игроков
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 8px; border: 1px solid #b97410; background-color: #030a0f;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; border: 1px solid #9a5e0d; background-color: #071016;">
                                <tr>
                                    <td style="padding: 7px 7px 0;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;">
                                            <tr>
                                                <td width="14" height="12" style="width: 14px; height: 12px; border-top: 1px solid #b97717; border-left: 1px solid #b97717; font-size: 0; line-height: 0;">&nbsp;</td>
                                                <td style="font-size: 0; line-height: 0;">&nbsp;</td>
                                                <td width="14" height="12" style="width: 14px; height: 12px; border-top: 1px solid #b97717; border-right: 1px solid #b97717; font-size: 0; line-height: 0;">&nbsp;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-card-content" align="center" style="padding: 44px 48px 38px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;">
                                            <tr>
                                                <td align="center">
                                                    <div style="width: 52px; height: 52px; margin: 0 auto 22px; border: 1px solid #b67a1d; border-radius: 50%; background-color: #101b20; color: #f8be2f; font-size: 28px; font-weight: 700; line-height: 52px; text-align: center;">
                                                        ↻
                                                    </div>

                                                    <p style="margin: 0 0 9px; color: #c99428; font-size: 11px; font-weight: 700; letter-spacing: 2.4px; line-height: 1.4; text-transform: uppercase;">
                                                        Безопасность аккаунта
                                                    </p>

                                                    <h1 class="email-title" style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2; text-align: center;">
                                                        Восстановление пароля
                                                    </h1>

                                                    <p style="margin: 13px 0 0; color: #f7ba2b; font-size: 20px; font-weight: 700; line-height: 1.35; text-align: center;">
                                                        Твоя гильдия. Твоя команда.
                                                    </p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding-top: 30px; color: #d8d9d8; font-size: 16px; line-height: 1.65; text-align: left;">
                                                    <p style="margin: 0 0 14px; color: #ffffff; font-size: 18px; font-weight: 700;">
                                                        Здравствуйте, {{ $userName }}!
                                                    </p>
                                                    <p style="margin: 0;">
                                                        Мы получили запрос на восстановление пароля для вашего аккаунта. Нажмите кнопку ниже, чтобы задать новый пароль.
                                                    </p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td align="center" style="padding: 30px 0 24px;">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation">
                                                        <tr>
                                                            <td align="center" bgcolor="#f7ba2b" style="border: 1px solid #ffd65b; border-radius: 5px; background-color: #f7ba2b; box-shadow: 0 6px 20px rgba(247, 186, 43, 0.16);">
                                                                <a href="{{ $resetUrl }}" target="_blank" rel="noopener" style="display: inline-block; min-width: 220px; padding: 15px 28px; color: #11100c; font-size: 16px; font-weight: 700; line-height: 1.2; text-align: center; text-decoration: none;">
                                                                    Сбросить пароль
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td align="center">
                                                    <p style="display: inline-block; margin: 0; padding: 8px 13px; border: 1px solid #334047; border-radius: 999px; color: #abb3b7; font-size: 12px; line-height: 1.4;">
                                                        Ссылка действительна {{ $expiresInMinutes }} минут
                                                    </p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding-top: 28px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; border-left: 3px solid #b4771c; background-color: #0c181e;">
                                                        <tr>
                                                            <td style="padding: 14px 16px; color: #aeb6ba; font-size: 13px; line-height: 1.55;">
                                                                Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо. Ваш пароль останется прежним.
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding-top: 28px; border-bottom: 1px solid #344148; font-size: 0; line-height: 0;">&nbsp;</td>
                                            </tr>

                                            <tr>
                                                <td style="padding-top: 22px; color: #879197; font-size: 12px; line-height: 1.55; text-align: left;">
                                                    <p style="margin: 0 0 8px;">
                                                        Если кнопка «Сбросить пароль» не открывается, скопируйте ссылку и вставьте её в адресную строку браузера:
                                                    </p>
                                                    <p style="margin: 0; word-break: break-all;">
                                                        <a href="{{ $resetUrl }}" style="color: #d8a52e; text-decoration: underline; word-break: break-all;">
                                                            {{ $resetUrl }}
                                                        </a>
                                                    </p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding-top: 28px; color: #c1c6c8; font-size: 14px; line-height: 1.55; text-align: left;">
                                                    <div style="margin-bottom: 3px;">С уважением,</div>
                                                    <strong style="color: #ffffff;">команда {{ $appName }}</strong>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 7px 7px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%;">
                                            <tr>
                                                <td width="14" height="12" style="width: 14px; height: 12px; border-bottom: 1px solid #b97717; border-left: 1px solid #b97717; font-size: 0; line-height: 0;">&nbsp;</td>
                                                <td style="font-size: 0; line-height: 0;">&nbsp;</td>
                                                <td width="14" height="12" style="width: 14px; height: 12px; border-right: 1px solid #b97717; border-bottom: 1px solid #b97717; font-size: 0; line-height: 0;">&nbsp;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 24px 20px 0; color: #68747a; font-size: 12px; line-height: 1.55;">
                            <p style="margin: 0;">
                                © {{ date('Y') }} {{ $appName }}. Собираем команды, покоряем рейды.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
