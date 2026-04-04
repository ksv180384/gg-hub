<?php

namespace Domains\Post\Actions;

use Stevebauman\Purify\Facades\Purify;

/**
 * Формирует превью поста: первые 20 слов с сохранением HTML-тегов.
 * Все теги в превью закрыты (незакрытые в исходном body исправляются перед извлечением).
 */
class BuildPostPreviewAction
{
    private const PREVIEW_WORDS_COUNT = 100;

    public function __invoke(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $fixedHtml = app(FixPostBodyHtmlAction::class)($body);
        $previewHtml = $this->extractHtmlPreview($fixedHtml);

        return Purify::config('guild_rich_text')->clean($previewHtml);
    }

    /**
     * Извлекает первые N слов из HTML с сохранением тегов. Все теги закрыты.
     */
    private function extractHtmlPreview(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__preview-wrap">' . $html . '</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $wrap = $dom->getElementById('__preview-wrap');
        if (!$wrap) {
            return $this->fallbackPlainPreview($html);
        }

        $wordsCollected = 0;
        $result = '';
        foreach ($wrap->childNodes as $child) {
            $result .= $this->extractHtmlFromNode($child, $wordsCollected, self::PREVIEW_WORDS_COUNT);
            if ($wordsCollected >= self::PREVIEW_WORDS_COUNT) {
                break;
            }
        }

        return trim($result);
    }

    /**
     * Рекурсивно обходит DOM, собирает HTML до достижения лимита слов. Все теги закрыты.
     */
    private function extractHtmlFromNode(\DOMNode $node, int &$wordsCollected, int $maxWords): string
    {
        if ($wordsCollected >= $maxWords) {
            return '';
        }

        if ($node instanceof \DOMText) {
            $words = preg_split('/\s+/u', $node->textContent ?? '', -1, PREG_SPLIT_NO_EMPTY);
            $toTake = min(\count($words), $maxWords - $wordsCollected);
            $wordsCollected += $toTake;
            $text = implode(' ', \array_slice($words, 0, $toTake));

            return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        }

        if ($node instanceof \DOMElement) {
            $tagName = \strtolower($node->tagName);
            if (\in_array($tagName, ['script', 'style'], true)) {
                return '';
            }
            // Видео (div с data-video-embed или iframe) — включаем в превью целиком, без учёта слов
            if (($tagName === 'div' && $node->hasAttribute('data-video-embed')) || $tagName === 'iframe') {
                return $this->nodeToHtml($node);
            }

            $html = '<' . $tagName;
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $html .= ' ' . $attr->name . '="' . htmlspecialchars($attr->value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8') . '"';
                }
            }
            $html .= '>';

            foreach ($node->childNodes as $child) {
                $html .= $this->extractHtmlFromNode($child, $wordsCollected, $maxWords);
                if ($wordsCollected >= $maxWords) {
                    break;
                }
            }

            $html .= '</' . $tagName . '>';

            return $html;
        }

        return '';
    }

    /**
     * Сериализует DOM-узел в HTML-строку (включая дочерние элементы).
     */
    private function nodeToHtml(\DOMNode $node): string
    {
        if (!$node instanceof \DOMElement) {
            return '';
        }
        $dom = $node->ownerDocument;
        if (!$dom) {
            return '';
        }
        $html = $dom->saveHTML($node);

        return $html ?: '';
    }

    private function fallbackPlainPreview(string $html): string
    {
        $text = strip_tags($html);
        $words = preg_split('/\s+/u', trim($text), self::PREVIEW_WORDS_COUNT + 1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', \array_slice($words, 0, self::PREVIEW_WORDS_COUNT));
    }
}
