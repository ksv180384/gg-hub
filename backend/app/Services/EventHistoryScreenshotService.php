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
}
