<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventHistoryScreenshotService
{
    private const MAX_SIZE = 1280;
    private const EXT = 'jpg';

    public function store(UploadedFile $file, int $eventHistoryId): string
    {
        $baseDir = 'event-history/' . $eventHistoryId . '/screenshots';
        $disk = Storage::disk('public');
        $disk->makeDirectory($baseDir);

        $path = $baseDir . '/' . Str::uuid() . '.' . self::EXT;
        $diskPath = $disk->path($path);

        $manager = app('image');
        $manager
            ->read($file->getRealPath())
            ->scaleDown(self::MAX_SIZE, self::MAX_SIZE)
            ->toJpeg(quality: 88)
            ->save($diskPath);

        return url($disk->url($path));
    }

    public function deleteByUrl(?string $url): void
    {
        if (! is_string($url) || $url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return;
        }

        $storagePrefix = '/storage/';
        $position = strpos($path, $storagePrefix);
        if ($position === false) {
            return;
        }

        $relativePath = substr($path, $position + strlen($storagePrefix));
        if (! str_starts_with($relativePath, 'event-history/')) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
    }
}
