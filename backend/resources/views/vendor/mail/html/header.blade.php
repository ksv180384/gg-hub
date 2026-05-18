@props(['url'])
@php
    $logoPath = config('mail.logo_path', public_path('images/mail/gg-hub-logo.png'));
    $logoAlt = config('mail.logo_alt', config('app.name', 'gg-hub'));
    $externalLogoUrl = config('mail.logo') ?: rtrim((string) config('app.frontend_url', config('app.url')), '/').'/images/mail/gg-hub-logo.png';

    if (config('mail.logo')) {
        $logoSrc = config('mail.logo');
    } elseif (is_file($logoPath)) {
        $logoSrc = isset($message)
            ? $message->embed($logoPath)
            : 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
    } else {
        $logoSrc = $externalLogoUrl;
    }
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logoSrc }}" class="logo" alt="{{ $logoAlt }}" width="70" height="70">
</a>
</td>
</tr>
