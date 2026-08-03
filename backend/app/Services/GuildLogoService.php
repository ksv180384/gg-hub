<?php

namespace App\Services;

use Domains\Guild\Models\Guild;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GuildLogoService
{
    private const CARD_SIZE = 350;

    /**
     * Сохраняет логотип в исходном размере и версию 350px для карточек.
     * Возвращает относительный путь к оригиналу для БД.
     */
    public function store(UploadedFile $file, Guild $guild): string
    {
        $this->delete($guild);

        $disk = Storage::disk('public');
        $dir = 'guilds/'.$guild->id;
        $stored = $dir.'/logo.webp';
        $cardPathRel = $dir.'/logo_card.webp';
        $disk->makeDirectory($dir);

        $manager = app('image');
        $image = $manager->read($file->getRealPath());
        $width = $image->width();
        $height = $image->height();
        $image->toWebp(quality: 90)->save($disk->path($stored));

        $image = $manager->read($file->getRealPath());
        if ($width <= $height) {
            $image->scale(width: self::CARD_SIZE);
        } else {
            $image->scale(height: self::CARD_SIZE);
        }
        $image->toWebp(quality: 88)->save($disk->path($cardPathRel));

        return $stored;
    }

    /**
     * Удаляет логотип (оригинал и версию 350px) с диска.
     */
    public function delete(Guild $guild): void
    {
        if (! $guild->logo_path) {
            return;
        }
        Storage::disk('public')->delete($guild->logo_path);
        $cardPath = self::cardPath($guild->logo_path);
        if ($cardPath) {
            Storage::disk('public')->delete($cardPath);
        }
        $dir = dirname($guild->logo_path);
        if (Storage::disk('public')->exists($dir)) {
            $files = Storage::disk('public')->files($dir);
            if (empty($files)) {
                Storage::disk('public')->deleteDirectory($dir);
            }
        }
    }

    /**
     * Путь к версии логотипа 350px по пути оригинала.
     */
    public static function cardPath(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }
        $pathInfo = pathinfo($logoPath);
        $ext = strtolower($pathInfo['extension'] ?? 'jpg');

        return ($pathInfo['dirname'] ? $pathInfo['dirname'].'/' : '').'logo_card.'.$ext;
    }

    /**
     * URL оригинального логотипа.
     */
    public static function url(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }

        return Storage::disk('public')->url($logoPath);
    }

    /**
     * URL логотипа 350px для карточек. Если версии нет — возвращает оригинал.
     */
    public static function urlCard(?string $logoPath): ?string
    {
        $cardPath = self::cardPath($logoPath);
        if (! $cardPath || ! Storage::disk('public')->exists($cardPath)) {
            return self::url($logoPath);
        }

        return Storage::disk('public')->url($cardPath);
    }
}
