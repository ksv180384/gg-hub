<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GameClassImageService
{
    private const PREVIEW_SIZE = 600;

    private const THUMB_SIZE = 100;

    /**
     * Сохраняет изображение класса в game_classes/{gameClassId}/images/
     *
     * @return string Путь к основному изображению (для записи в БД)
     */
    public function storeWithVariants(UploadedFile $file, int $gameClassId): string
    {
        $baseDir = 'game_classes/'.$gameClassId.'/images';
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

    public static function previewPath(string $mainPath): string
    {
        $pathInfo = pathinfo($mainPath);
        $ext = $pathInfo['extension'] ?? 'jpg';

        return ($pathInfo['dirname'] ? $pathInfo['dirname'].'/' : '').$pathInfo['filename'].'_600.'.$ext;
    }

    public static function thumbPath(string $mainPath): string
    {
        $pathInfo = pathinfo($mainPath);
        $ext = $pathInfo['extension'] ?? 'jpg';

        return ($pathInfo['dirname'] ? $pathInfo['dirname'].'/' : '').$pathInfo['filename'].'_100.'.$ext;
    }
}
