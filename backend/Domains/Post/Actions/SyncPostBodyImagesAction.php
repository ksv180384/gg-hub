<?php

namespace Domains\Post\Actions;

use Domains\Post\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $wrapped = '<div id="__post-wrap">'.$html.'</div>';

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapped,
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $wrap = $dom->getElementById('__post-wrap');
        if (! $wrap) {
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
                // Keep unsupported src values unchanged.
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
        $postDir = 'post/'.$post->id.'/';

        if (Str::startsWith($src, ['http://', 'https://'])) {
            $path = parse_url($src, PHP_URL_PATH);
            if (! is_string($path) || $path === '') {
                return null;
            }
        } else {
            $path = $src;
        }

        // Public disk URLs normally start with /storage/.
        if (Str::startsWith($path, '/storage/')) {
            $rel = ltrim(Str::after($path, '/storage/'), '/');

            return Str::startsWith($rel, $postDir) ? $rel : null;
        }

        // Also accept relative post/{id}/... paths.
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
        if (! preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $src, $m)) {
            return null;
        }

        $base64 = Str::after($src, ',');
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        return $this->storeAndResizeBinary($post, $binary);
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

        if (! $response->successful()) {
            return null;
        }

        $binary = $response->body();
        if ($binary === '') {
            return null;
        }

        return $this->storeAndResizeBinary($post, $binary);
    }

    /**
     * @return array{path: string, was_created: bool}|null
     */
    private function storeAndResizeBinary(Post $post, string $binary): ?array
    {
        $dir = 'post/'.$post->id;
        $hash = sha1($binary);
        $filename = $hash.'.webp';
        $relativePath = $dir.'/'.$filename;

        // Deduplicate by source content.
        if (Storage::disk('public')->exists($relativePath)) {
            return ['path' => $relativePath, 'was_created' => false];
        }

        Storage::disk('public')->makeDirectory($dir);
        $absolutePath = Storage::disk('public')->path($relativePath);

        $manager = app('image');
        $manager
            ->read($binary)
            ->scaleDown(self::MAX_SIZE, self::MAX_SIZE)
            ->toWebp(quality: 88)
            ->save($absolutePath);

        return ['path' => $relativePath, 'was_created' => true];
    }

    /**
     * @param  array<int, string>  $referencedRelativePaths
     */
    private function cleanupUnusedImages(Post $post, array $referencedRelativePaths): void
    {
        $dir = 'post/'.$post->id;
        $referenced = collect($referencedRelativePaths)
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn (string $p) => ltrim($p, '/'))
            ->unique()
            ->values()
            ->all();

        $files = Storage::disk('public')->files($dir);
        foreach ($files as $file) {
            if (! in_array($file, $referenced, true)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
