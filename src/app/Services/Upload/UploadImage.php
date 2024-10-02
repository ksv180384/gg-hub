<?php

namespace App\Services\Upload;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadImage
{
    function saveImageFromUrl(string $url, string $filename = '', string $path = ''): string
    {
        $context = stream_context_create([
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ]);
        // Получаем содержимое изображения
        $imageContent = file_get_contents($url, false, $context);

        // Проверяем, удалось ли получить содержимое
        if ($imageContent === false) {
            throw ValidationException::withMessages(['message' => 'Не удалось получить изображение по URL: ' . $url]);
        }

        $path = trim($path, '/') . '/';
        if(!$filename){
            $filename = basename($url);
        }
        else {
            $pathUrl = parse_url($url, PHP_URL_PATH);
            $extension = pathinfo($pathUrl, PATHINFO_EXTENSION);
            $filename = $filename . '.' . $extension;
        }

        $fullPath = $path  . $filename;

        // Сохраняем изображение в хранилище
        Storage::put($fullPath, $imageContent);

        return $fullPath; // Возвращаем URL сохраненного изображения
    }
}
