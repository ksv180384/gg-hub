<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GameImageService
{
    private const PREVIEW_SIZE = 600;
    private const THUMB_SIZE = 100;

    /**
     * Сохраняет полноразмерное изображение и создаёт превью 600px и мини 100px.
     * Файлы сохраняются в games/{gameId}/images/
     *
     * @return string Путь к основному изображению (для записи в БД)
     */
    public function storeWithVariants(UploadedFile $file, int $gameId): string
    {
        $baseDir = 'games/' . $gameId . '/images';
        $fullPath = $file->store($baseDir, 'public');
        $fullPathDisk = Storage::disk('public')->path($fullPath);

        $pathInfo = \pathinfo($fullPath);
        $dir = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $ext = \strtolower($pathInfo['extension'] ?? 'jpg');

        $previewPathRel = $dir . '/' . $filename . '_600.' . $ext;
        $thumbPathRel = $dir . '/' . $filename . '_100.' . $ext;
        $previewPathDisk = Storage::disk('public')->path($previewPathRel);
        $thumbPathDisk = Storage::disk('public')->path($thumbPathRel);

        try {
            $manager = app('image');
            $image = $manager->read($fullPathDisk);
            $image->scaleDown(self::PREVIEW_SIZE, self::PREVIEW_SIZE)->save($previewPathDisk);
            $image = $manager->read($fullPathDisk);
            $image->scaleDown(self::THUMB_SIZE, self::THUMB_SIZE)->save($thumbPathDisk);
        } catch (\Throwable $e) {
            // Драйвер (GD/Imagick) недоступен — сохраняем только оригинал
        }

        return $fullPath;
    }

    /**
     * Возвращает путь к превью 600px по основному пути.
     */
    public static function previewPath(string $mainPath): string
    {
        $pathInfo = \pathinfo($mainPath);
        $ext = $pathInfo['extension'] ?? 'jpg';
        return ($pathInfo['dirname'] ? $pathInfo['dirname'] . '/' : '') . $pathInfo['filename'] . '_600.' . $ext;
    }

    /**
     * Возвращает путь к мини 100px по основному пути.
     */
    public static function thumbPath(string $mainPath): string
    {
        $pathInfo = \pathinfo($mainPath);
        $ext = $pathInfo['extension'] ?? 'jpg';
        return ($pathInfo['dirname'] ? $pathInfo['dirname'] . '/' : '') . $pathInfo['filename'] . '_100.' . $ext;
    }
}
