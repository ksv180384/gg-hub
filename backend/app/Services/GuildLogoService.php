<?php

namespace App\Services;

use Domains\Guild\Models\Guild;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GuildLogoService
{
    /**
     * Сохраняет логотип гильдии в public/guilds/{id}/ и возвращает относительный путь для БД.
     */
    public function store(UploadedFile $file, Guild $guild): string
    {
        $this->delete($guild);
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $path = 'guilds/' . $guild->id . '/logo.' . strtolower($ext);
        $stored = $file->storeAs('guilds/' . $guild->id, 'logo.' . strtolower($ext), 'public');
        return $stored;
    }

    /**
     * Удаляет логотип гильдии с диска.
     */
    public function delete(Guild $guild): void
    {
        if (!$guild->logo_path) {
            return;
        }
        Storage::disk('public')->delete($guild->logo_path);
        $dir = dirname($guild->logo_path);
        if (Storage::disk('public')->exists($dir)) {
            $files = Storage::disk('public')->files($dir);
            if (empty($files)) {
                Storage::disk('public')->deleteDirectory($dir);
            }
        }
    }

    /**
     * Возвращает URL логотипа для отдачи клиенту.
     */
    public static function url(?string $logoPath): ?string
    {
        if (!$logoPath) {
            return null;
        }
        return Storage::disk('public')->url($logoPath);
    }
}
