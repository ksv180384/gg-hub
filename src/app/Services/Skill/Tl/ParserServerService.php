<?php

namespace App\Services\Skill\Tl;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ParserServerService
{
    private string $parserServerPath;

    public function __construct()
    {
        $this->parserServerPath = env('PARSER_SERVER_PATH');
    }

    public function getSkillsAll()
    {
        $response = Http::get($this->parserServerPath . '/skills/all');

        if (!$response->successful()) {
            throw ValidationException::withMessages(['message' => 'Ошибка при получении данных с парсер сервера.']);
        }
        $res = $response->json();

        return !empty($res['skills']) ? $res['skills'] : [];
    }
}
