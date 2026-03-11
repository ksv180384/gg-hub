<?php

namespace Domains\Post\Actions;

/**
 * Закрывает незакрытые HTML-теги в теле поста, чтобы вёрстка не ломалась.
 * Нормализует URL видео: YouTube — enablejsapi=1, VK — js_api=1 для работы API.
 */
class FixPostBodyHtmlAction
{
    public function __invoke(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $wrapped = '<div id="__post-wrap">' . $html . '</div>';

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $wrap = $dom->getElementById('__post-wrap');
        if (!$wrap) {
            return $html;
        }

        $this->normalizeVideoIframeUrls($dom);

        $result = '';
        foreach ($wrap->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    /**
     * Добавляет enablejsapi=1 в YouTube embed и js_api=1 в VK embed.
     */
    private function normalizeVideoIframeUrls(\DOMDocument $dom): void
    {
        $iframes = $dom->getElementsByTagName('iframe');
        foreach ($iframes as $iframe) {
            $src = $iframe->getAttribute('src');
            if ($src === '') {
                continue;
            }
            $normalized = $this->ensureVideoApiParams($src);
            if ($normalized !== $src) {
                $iframe->setAttribute('src', $normalized);
            }
        }

        // data-src в div[data-video-embed] (если iframe внутри)
        $xpath = new \DOMXPath($dom);
        $divs = $xpath->query('//div[@data-video-embed]');
        foreach ($divs as $div) {
            $dataSrc = $div->getAttribute('data-src');
            if ($dataSrc !== '') {
                $normalized = $this->ensureVideoApiParams($dataSrc);
                if ($normalized !== $dataSrc) {
                    $div->setAttribute('data-src', $normalized);
                }
            }
            $nested = $div->getElementsByTagName('iframe')->item(0);
            if ($nested) {
                $src = $nested->getAttribute('src');
                if ($src !== '') {
                    $normalized = $this->ensureVideoApiParams($src);
                    if ($normalized !== $src) {
                        $nested->setAttribute('src', $normalized);
                    }
                }
            }
        }
    }

    private function ensureVideoApiParams(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['host'])) {
            return $url;
        }
        $host = strtolower($parsed['host']);
        $isYoutube = (str_contains($host, 'youtube.com') || str_contains($host, 'youtube-nocookie.com')) && str_contains($url, '/embed/');
        $isVk = str_contains($host, 'vk.com') && str_contains($url, 'video_ext');

        if ($isYoutube && !str_contains($url, 'enablejsapi')) {
            $sep = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . 'enablejsapi=1';
        }
        if ($isVk && !preg_match('/[?&]js_api\b/', $url)) {
            $sep = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . 'js_api=1';
        }

        return $url;
    }
}
