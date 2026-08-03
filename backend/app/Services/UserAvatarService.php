<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserAvatarService
{
    private const LARGE_SIZE = 1024;

    private const SMALL_SIZE = 300;

    private const EXT = 'webp';

    /**
     * Сохраняет аватар в users/{userId}/avatar/ в двух размерах:
     * large.webp — макс. 1024×1024, small.webp — макс. 300×300.
     * Сохраняем в WebP для единообразия.
     *
     * @return string Путь к папке аватара (users/{id}/avatar) для записи в user.avatar
     */
    public function storeAvatar(UploadedFile $file, int $userId): string
    {
        $baseDir = 'users/'.$userId.'/avatar';
        $disk = Storage::disk('public');

        $disk->makeDirectory($baseDir);
        $largePath = $baseDir.'/large.'.self::EXT;
        $smallPath = $baseDir.'/small.'.self::EXT;
        $largePathDisk = $disk->path($largePath);
        $smallPathDisk = $disk->path($smallPath);

        $manager = app('image');
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(self::LARGE_SIZE, self::LARGE_SIZE)->toWebp(quality: 90)->save($largePathDisk);
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(self::SMALL_SIZE, self::SMALL_SIZE)->toWebp(quality: 85)->save($smallPathDisk);

        return $baseDir;
    }

    /**
     * Путь к большой версии аватара (1024px).
     */
    public static function largePath(string $avatarDir): string
    {
        return self::variantPath($avatarDir, 'large');
    }

    /**
     * Path to the small avatar variant used in the interface.
     */
    public static function smallPath(string $avatarDir): string
    {
        return self::variantPath($avatarDir, 'small');
    }

    private static function variantPath(string $avatarDir, string $variant): string
    {
        $basePath = rtrim($avatarDir, '/').'/'.$variant;
        $webpPath = $basePath.'.'.self::EXT;

        return Storage::disk('public')->exists($webpPath)
            ? $webpPath
            : $basePath.'.jpg';
    }

    /**
     * Удаляет папку аватара и все файлы внутри.
     */
    public function deleteAvatar(string $avatarDir): void
    {
        Storage::disk('public')->deleteDirectory($avatarDir);
    }
}
