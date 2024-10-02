<?php

namespace App\Services\Skill\Tl;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SkillTranslatorService
{
    private $translatorServerPath;

    public function __construct()
    {
        $this->translatorServerPath = env('TRANSLATOR_SERVER_PATH');
    }

    public function translator(string $text)
    {
        $response = Http::get($this->translatorServerPath . '/translate', [
            'text' => $text,
            'source_lang' => 'en',
            'target_lang' => 'ru',
        ]);

        if (!$response->successful()) {
            throw ValidationException::withMessages(['message' => 'Ошибка при получении данных перевода.']);
        }
        $res = $response->json();

        return !empty($res['message']) ? $res['message'] : '';
    }
}
