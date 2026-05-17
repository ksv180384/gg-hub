<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncPostBodyImagesAction
{
    private const MAX_SIZE = 1280;
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const TIMEOUT_SECONDS = 15;

    /**
     * Синхронизирует изображения в HTML тела поста:
     * - base64 и внешние ссылки скачивает/сохраняет в storage (public) в post/{id}
     * - заменяет src на локальный URL
     * - удаляет изображения в папке post/{id}, на которые больше нет ссылок в body
     *
     * @return array{html: string, created: array<int, string>, referenced: array<int, string>}
     */
    public function __invoke(Post $post, string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            $this->cleanupUnusedImages($post, []);

            return [
                'html' => '',
                'created' => [],
                'referenced' => [],
            ];
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

        $referencedRelativePaths = [];
        $createdRelativePaths = [];

        /** @var \DOMNodeList<\DOMElement> $images */
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = trim((string) $img->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            $localRelative = $this->extractLocalRelativePath($post, $src);
            if ($localRelative !== null) {
                $referencedRelativePaths[] = $localRelative;
                continue;
            }

            $stored = $this->storeImageFromSrc($post, $src);
            if ($stored === null) {
                // Неизвестный формат src — оставляем как есть (например, уже очищенный Purify пустой src)
                continue;
            }

            $referencedRelativePaths[] = $stored['path'];
            if ($stored['was_created'] === true) {
                $createdRelativePaths[] = $stored['path'];
            }
            $img->setAttribute('src', Storage::disk('public')->url($stored['path']));
        }

        $result = '';
        foreach ($wrap->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        $this->cleanupUnusedImages($post, $referencedRelativePaths);

        return [
            'html' => $result,
            'created' => $createdRelativePaths,
            'referenced' => $referencedRelativePaths,
        ];
    }

    private function extractLocalRelativePath(Post $post, string $src): ?string
    {
        $postDir = 'post/' . $post->id . '/';

        if (Str::startsWith($src, ['http://', 'https://'])) {
            $path = parse_url($src, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return null;
            }
        } else {
            $path = $src;
        }

        // disk('public')->url(...) обычно отдаёт /storage/...
        if (Str::startsWith($path, '/storage/')) {
            $rel = ltrim(Str::after($path, '/storage/'), '/');
            return Str::startsWith($rel, $postDir) ? $rel : null;
        }

        // На случай если в контенте лежит относительный путь post/{id}/...
        $trimmed = ltrim($path, '/');
        if (Str::startsWith($trimmed, $postDir)) {
            return $trimmed;
        }

        return null;
    }

    /**
     * @return array{path: string, was_created: bool}|null
     */
    private function storeImageFromSrc(Post $post, string $src): ?array
    {
        if (Str::startsWith($src, 'data:image/')) {
            $stored = $this->storeBase64Image($post, $src);
            if ($stored === null) {
                throw ValidationException::withMessages([
                    'body' => ['Не удалось сохранить изображение из буфера обмена. Попробуйте вставить его ещё раз.'],
                ]);
            }

            return $stored;
        }

        if (Str::startsWith($src, ['http://', 'https://'])) {
            $stored = $this->storeRemoteImage($post, $src);
            if ($stored === null) {
                throw ValidationException::withMessages([
                    'body' => ['Не удалось скачать и сохранить изображение по ссылке. Проверьте, что ссылка ведёт на картинку и доступна без авторизации.'],
                ]);
            }

            return $stored;
        }

        return null;
    }

    /**
     * @return array{path: string, was_created: bool}|null
     */
    private function storeBase64Image(Post $post, string $src): ?array
    {
        if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $src, $m)) {
            return null;
        }

        $mimeExt = strtolower((string) ($m[1] ?? 'jpg'));
        $mimeExt = $mimeExt === 'jpeg' ? 'jpg' : $mimeExt;

        $base64 = Str::after($src, ',');
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        return $this->storeAndResizeBinary($post, $binary, $mimeExt);
    }

    /**
     * @return array{path: string, was_created: bool}|null
     */
    private function storeRemoteImage(Post $post, string $url): ?array
    {
        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $contentType = (string) $response->header('Content-Type', '');
        $ext = $this->guessExtensionFromContentType($contentType) ?? 'jpg';

        $binary = $response->body();
        if ($binary === '') {
            return null;
        }

        return $this->storeAndResizeBinary($post, $binary, $ext);
    }

    private function guessExtensionFromContentType(string $contentType): ?string
    {
        $ct = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        return match ($ct) {
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    /**
     * @return array{path: string, was_created: bool}|null
     */
    private function storeAndResizeBinary(Post $post, string $binary, string $ext): ?array
    {
        $ext = strtolower($ext);
        if (!in_array($ext, ['jpg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $dir = 'post/' . $post->id;
        $hash = sha1($binary);
        $filename = $hash . '.' . $ext;
        $relativePath = $dir . '/' . $filename;

        // Дедупликация по контенту: если файл уже есть — просто используем его
        if (Storage::disk('public')->exists($relativePath)) {
            return ['path' => $relativePath, 'was_created' => false];
        }

        Storage::disk('public')->makeDirectory($dir);
        $absolutePath = Storage::disk('public')->path($relativePath);

        try {
            $manager = app('image');
            $image = $manager->read($binary);
            $image->scaleDown(self::MAX_SIZE, self::MAX_SIZE)->save($absolutePath);
        } catch (\Throwable) {
            // Если обработка недоступна — сохраняем как есть
            Storage::disk('public')->put($relativePath, $binary);
        }

        return ['path' => $relativePath, 'was_created' => true];
    }

    /**
     * @param array<int, string> $referencedRelativePaths
     */
    private function cleanupUnusedImages(Post $post, array $referencedRelativePaths): void
    {
        $dir = 'post/' . $post->id;
        $referenced = collect($referencedRelativePaths)
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn (string $p) => ltrim($p, '/'))
            ->unique()
            ->values()
            ->all();

        $files = Storage::disk('public')->files($dir);
        foreach ($files as $file) {
            if (!in_array($file, $referenced, true)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}

