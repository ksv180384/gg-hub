<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CharacterAvatarService
{
    private const LARGE_SIZE = 1024;
    private const SMALL_SIZE = 300;
    private const EXT = 'jpg';

    /**
     * Сохраняет аватар в characters/{characterId}/avatar/ в двух размерах.
     *
     * @return string Путь к папке аватара для записи в character.avatar
     */
    public function storeAvatar(UploadedFile $file, int $characterId): string
    {
        $baseDir = 'characters/' . $characterId . '/avatar';
        $disk = Storage::disk('public');

        $disk->makeDirectory($baseDir);
        $largePath = $baseDir . '/large.' . self::EXT;
        $smallPath = $baseDir . '/small.' . self::EXT;
        $largePathDisk = $disk->path($largePath);
        $smallPathDisk = $disk->path($smallPath);

        $manager = app('image');
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(self::LARGE_SIZE, self::LARGE_SIZE)->toJpeg(quality: 90)->save($largePathDisk);
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(self::SMALL_SIZE, self::SMALL_SIZE)->toJpeg(quality: 85)->save($smallPathDisk);

        return $baseDir;
    }

    public static function largePath(string $avatarDir): string
    {
        return rtrim($avatarDir, '/') . '/large.' . self::EXT;
    }

    public static function smallPath(string $avatarDir): string
    {
        return rtrim($avatarDir, '/') . '/small.' . self::EXT;
    }

    public function deleteAvatar(string $avatarDir): void
    {
        Storage::disk('public')->deleteDirectory($avatarDir);
    }
}
