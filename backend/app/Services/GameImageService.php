<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $baseDir = 'games/'.$gameId.'/images';
        $disk = Storage::disk('public');
        $disk->makeDirectory($baseDir);

        $fullPath = $baseDir.'/'.Str::uuid().'.webp';
        $fullPathDisk = $disk->path($fullPath);
        $previewPathRel = self::previewPath($fullPath);
        $thumbPathRel = self::thumbPath($fullPath);

        $manager = app('image');
        $manager->read($file->getRealPath())->toWebp(quality: 90)->save($fullPathDisk);
        $manager
            ->read($file->getRealPath())
            ->scaleDown(self::PREVIEW_SIZE, self::PREVIEW_SIZE)
            ->toWebp(quality: 88)
            ->save($disk->path($previewPathRel));
        $manager
            ->read($file->getRealPath())
            ->scaleDown(self::THUMB_SIZE, self::THUMB_SIZE)
            ->toWebp(quality: 85)
            ->save($disk->path($thumbPathRel));

        return $fullPath;
    }

    /**
     * Возвращает путь к превью 600px по основному пути.
     */
    public static function previewPath(string $mainPath): string
    {
        $pathInfo = \pathinfo($mainPath);
        $ext = $pathInfo['extension'] ?? 'jpg';

        return ($pathInfo['dirname'] ? $pathInfo['dirname'].'/' : '').$pathInfo['filename'].'_600.'.$ext;
    }

    /**
     * Возвращает путь к мини 100px по основному пути.
     */
    public static function thumbPath(string $mainPath): string
    {
        $pathInfo = \pathinfo($mainPath);
        $ext = $pathInfo['extension'] ?? 'jpg';

        return ($pathInfo['dirname'] ? $pathInfo['dirname'].'/' : '').$pathInfo['filename'].'_100.'.$ext;
    }
}
